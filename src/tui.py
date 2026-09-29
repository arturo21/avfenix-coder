# -*- coding: utf-8 -*-
"""
tui.py - Interfaz de Usuario Multitabs en Terminal (TUI) con Textual.
Soporta múltiples conversaciones con contextos independientes, persistencia de sesión,
failover multi-API (OpenRouter/free ➔ AnyAPI), catálogo visual de fuentes indexadas
y ejecución universal de herramientas XML.
"""

import os
import sys
import re
import json
import openai
from typing import Dict, Any, List, Optional

from textual.app import App, ComposeResult
from textual.containers import Vertical, Horizontal
from textual.widgets import Header, Footer, RichLog, Button, Label, TabbedContent, TabPane, TextArea
from textual import work, events
from textual.message import Message

from src.config import (
    OPENROUTER_API_KEY, ANYAPI_API_KEY, get_available_free_models,
    select_best_free_model, FALLBACK_FREE_MODELS, get_provider_endpoints
)
from src.prompts import SYSTEM_PROMPT
from src.tools import (
    read_file, write_file, patch_file, make_directory, list_directory, move_file,
    delete_file, execute_command, search_code, find_files, run_tests, git_status,
    create_backup, get_file_info, fetch_web_page, tree_directory, set_working_dir, add_context,
    add_source, list_sources, search_sources, remove_source,
    install_plugin_package, list_installed_plugins, uninstall_plugin_package
)
from src.session_manager import SessionManager
from src.sources_manager import SourcesManager
from src.plugins.plugin_manager import PluginManager
from src.plugins.package_manager import PackageManager


class ChatInput(TextArea):
    """
    Campo de entrada multilínea optimizado para chat con historial navegable (Arriba/Abajo).
    Enter envía el mensaje; Shift+Enter inserta un salto de línea.
    """
    class Submitted(Message):
        """Mensaje emitido al presionar Enter."""
        def __init__(self, chat_input: "ChatInput") -> None:
            super().__init__()
            self.chat_input = chat_input
            self.value = chat_input.text

    def __init__(self, **kwargs):
        super().__init__(show_line_numbers=False, **kwargs)
        self.prompt_history: List[str] = []
        self.prompt_history_index: int = 0
        self.current_draft: str = ""

    @property
    def value(self) -> str:
        return self.text

    @value.setter
    def value(self, val: str) -> None:
        self.text = val

    def on_key(self, event: events.Key) -> None:
        if event.key == "enter":
            event.prevent_default()
            event.stop()
            if self.text.strip():
                self.post_message(self.Submitted(self))
        elif event.key == "shift+enter":
            event.prevent_default()
            event.stop()
            self.insert("\n")
        elif event.key == "up":
            if self.cursor_location[0] == 0:
                if self.prompt_history:
                    event.prevent_default()
                    event.stop()
                    if self.prompt_history_index == len(self.prompt_history):
                        self.current_draft = self.text
                    if self.prompt_history_index > 0:
                        self.prompt_history_index -= 1
                        self.text = self.prompt_history[self.prompt_history_index]
                        lines = self.text.split("\n")
                        self.cursor_location = (len(lines) - 1, len(lines[-1]))
        elif event.key == "down":
            lines = self.text.split("\n")
            if self.cursor_location[0] == len(lines) - 1:
                if self.prompt_history_index < len(self.prompt_history):
                    event.prevent_default()
                    event.stop()
                    self.prompt_history_index += 1
                    if self.prompt_history_index == len(self.prompt_history):
                        self.text = self.current_draft
                    else:
                        self.text = self.prompt_history[self.prompt_history_index]
                    lines_new = self.text.split("\n")
                    self.cursor_location = (len(lines_new) - 1, len(lines_new[-1]))


class AVFenixApp(App):
    CSS = """
    Screen {
        background: #1e1e2e;
    }
    #sidebar {
        width: 32;
        background: #11111b;
        border-right: tall #89b4fa;
        padding: 1 2;
    }
    #chat-container {
        width: 1fr;
        padding: 1;
    }
    #chat-tabs {
        height: 1fr;
        background: #181825;
        border: solid #45475a;
        margin-bottom: 1;
    }
    TabPane {
        height: 1fr;
        padding: 0;
    }
    RichLog {
        height: 1fr;
        border: none;
        background: #181825;
    }
    #input-container {
        height: 3;
        layout: horizontal;
    }
    ChatInput {
        width: 1fr;
        height: 3;
        border: tall #89b4fa;
        background: #313244;
        color: #cdd6f4;
    }
    #send-btn {
        width: 16;
        height: 3;
        background: #89b4fa;
        color: #11111b;
        border: tall #89b4fa;
        text-style: bold;
        margin-left: 1;
    }
    #send-btn:hover {
        background: #b4befe;
        border: tall #b4befe;
        color: #11111b;
        text-style: bold;
    }
    #new-tab-btn {
        margin-top: 1;
        width: 100%;
        background: #a6e3a1;
        color: #11111b;
        border: none;
    }
    #new-tab-btn:hover {
        background: #94e2d5;
    }
    .status-ok {
        color: #a6e3a1;
        text-style: bold;
    }
    .status-loading {
        color: #f9e2af;
        text-style: bold;
    }
    .sidebar-title {
        color: #89b4fa;
        text-style: bold;
        margin-bottom: 1;
    }
    .history-area {
        background: #1e1e2e;
        border: dashed #45475a;
        height: 8;
        margin-top: 1;
        padding: 0 1;
    }
    .sources-area {
        background: #181825;
        border: solid #45475a;
        height: 8;
        margin-top: 1;
        padding: 0 1;
    }
    .tab-header-bar {
        height: 3;
        background: #11111b;
        border-bottom: solid #45475a;
        padding: 0 1;
        layout: horizontal;
        align: left middle;
    }
    .tab-title-text {
        width: auto;
        color: #cdd6f4;
        text-style: bold;
        margin-right: 2;
    }
    .close-tab-btn {
        background: #f38ba8;
        color: #11111b;
        border: none;
        text-style: bold;
        width: 6;
        height: 1;
        margin-top: 1;
    }
    .close-tab-btn:hover {
        background: #e78284;
        color: #11111b;
    }
    .copy-tab-btn {
        background: #fab387;
        color: #11111b;
        border: none;
        text-style: bold;
        width: 11;
        height: 1;
        margin-top: 1;
        margin-right: 1;
    }
    .copy-tab-btn:hover {
        background: #f9e2af;
        color: #11111b;
    }
    """

    TITLE = "AVFenix Coder"
    SUBTITLE = "Agente Autónomo de Codificación - Sistema Multitabs"
    BINDINGS = [
        ("q", "quit", "Salir"),
        ("n", "new_tab", "Nueva Conversación")
    ]

    def __init__(self):
        super().__init__()
        self.selected_model = "openrouter/free"
        self.providers = []
        self.active_provider_name = "Desconectado"
        self.candidates = FALLBACK_FREE_MODELS
        self.session_mgr = SessionManager()
        self.sources_mgr = SourcesManager()
        self.plugin_mgr = PluginManager()
        self.plugin_mgr.load_all_plugins()

        self.tab_counter = 1
        self.conversations: Dict[str, Dict[str, Any]] = {
            "tab-1": {
                "title": "Conversación 1",
                "chat_history": []
            }
        }

    def compose(self) -> ComposeResult:
        yield Header(show_clock=True)
        with Horizontal():
            with Vertical(id="sidebar"):
                yield Label("🦅 AVFENIX CODER", classes="sidebar-title")
                yield Label("[bold]API Status:[/bold]")
                self.status_label = Label("🔍 Iniciando...", classes="status-loading")
                yield self.status_label
                yield Label("\n[bold]Modelo Activo:[/bold]")
                self.model_label = Label("Cargando...", classes="status-loading")
                yield self.model_label

                yield Button("🆕 Nueva Conversación", variant="success", id="new-tab-btn")

                yield Label("\n[bold]📚 Fuentes Indexadas:[/bold]")
                self.sources_log = RichLog(classes="sources-area", highlight=True, markup=True)
                yield self.sources_log

                yield Label("\n[bold]Historial de Acciones:[/bold]")
                self.action_log = RichLog(classes="history-area", highlight=True, markup=True)
                yield self.action_log
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nEnter para enviar. Shift+Enter salto. Pestañas e historiales aislados.")

            with Vertical(id="chat-container"):
                with TabbedContent(id="chat-tabs"):
                    with TabPane("Conversación 1", id="tab-1"):
                        yield Horizontal(
                            Label("💬 Conversación 1", classes="tab-title-text"),
                            Button("📋 Copiar", id="copy-btn-tab-1", classes="copy-tab-btn"),
                            Button("❌", id="close-btn-tab-1", classes="close-tab-btn"),
                            classes="tab-header-bar"
                        )
                        yield RichLog(id="log-tab-1", highlight=True, markup=True)

                with Horizontal(id="input-container"):
                    self.user_input = ChatInput(placeholder="Pídele crear, editar, buscar o ejecutar herramientas...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    async def on_mount(self) -> None:
        self.initialize_agent()
        self.refresh_sources_widget()

        # Intentar restaurar sesión previa
        session_data = self.session_mgr.load_session()
        if session_data:
            await self.restore_session_state(session_data)
        else:
            self.write_to_tab(
                "tab-1",
                "[bold green]¡Bienvenido a AVFenix Coder Multitabs![/bold green]\n"
                "Cada pestaña posee una conversación y contexto independientes.\n"
            )

    def refresh_sources_widget(self) -> None:
        """Actualiza el widget visual de Fuentes Indexadas en la barra lateral."""
        try:
            self.sources_log.clear()
            sources_summary = self.sources_mgr.list_sources()
            self.sources_log.write(sources_summary)
        except Exception:
            pass

    async def restore_session_state(self, data: dict) -> None:
        """Restaura dinámicamente pestañas, historiales y conversaciones aisladas."""
        try:
            self.tab_counter = data.get("tab_counter", 1)
            self.user_input.prompt_history = data.get("prompt_history", [])
            self.user_input.prompt_history_index = len(self.user_input.prompt_history)

            saved_conversations = data.get("conversations", {})
            if not saved_conversations:
                return

            self.conversations = saved_conversations
            tabbed_content = self.query_one(TabbedContent)

            first_tab = True
            for tab_id, tab_info in saved_conversations.items():
                tab_title = tab_info.get("title", f"Conversación {tab_id}")
                chat_history = tab_info.get("chat_history", [])

                if first_tab:
                    first_tab = False
                    log_widget = self.query_one("#log-tab-1", RichLog)
                    self.populate_log_history(log_widget, chat_history)
                else:
                    header_bar = Horizontal(
                        Label(f"💬 {tab_title}", classes="tab-title-text"),
                        Button("📋 Copiar", id=f"copy-btn-{tab_id}", classes="copy-tab-btn"),
                        Button("❌", id=f"close-btn-{tab_id}", classes="close-tab-btn"),
                        classes="tab-header-bar"
                    )
                    log_widget = RichLog(id=f"log-{tab_id}", highlight=True, markup=True)
                    new_pane = TabPane(tab_title, header_bar, log_widget, id=tab_id)
                    await tabbed_content.add_pane(new_pane)
                    self.populate_log_history(log_widget, chat_history)

            active_tab = data.get("active_tab_id", "tab-1")
            if active_tab in saved_conversations:
                tabbed_content.active = active_tab

            self.action_log.write(f"💾 [green]Sesión multitab restaurada ({len(saved_conversations)} pestañas).[/green]")
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo restaurar la sesión: {e}")

    def populate_log_history(self, log_widget: RichLog, history: list) -> None:
        """Renderiza las entradas de historial restauradas en el widget de log."""
        log_widget.write("[bold gray][Sesión Restaurada][/bold gray]")
        for msg in history:
            role = msg.get("role")
            content = msg.get("content", "")
            if role == "user":
                log_widget.write(f"\n[bold cyan]Tú:[/bold cyan] {content}")
            elif role == "assistant":
                log_widget.write(f"\n[bold green]AVFenix Coder:[/bold green]\n{content}")
            elif role == "system":
                log_widget.write(f"\n[bold gray][Sistema]:[/bold gray]\n{content}")

    def auto_save(self) -> None:
        """Guarda automáticamente el estado completo de todas las pestañas."""
        try:
            tabbed_content = self.query_one(TabbedContent)
            active_tab = tabbed_content.active
        except Exception:
            active_tab = "tab-1"

        self.session_mgr.save_session(
            conversations=self.conversations,
            tab_counter=self.tab_counter,
            active_tab_id=active_tab,
            prompt_history=self.user_input.prompt_history
        )

    def on_unmount(self) -> None:
        """Guarda automáticamente la sesión al cerrar la TUI."""
        self.auto_save()

    @work(thread=True)
    def initialize_agent(self) -> None:
        provider_configs = get_provider_endpoints()
        self.providers = []

        headers = {
            "HTTP-Referer": "https://github.com/AVFenixCoder/AVFenixCoder",
            "X-Title": "AVFenix Coder Terminal Agent"
        }

        for p in provider_configs:
            try:
                client = openai.OpenAI(
                    base_url=p["base_url"],
                    api_key=p["api_key"],
                    default_headers=headers
                )
                self.providers.append({
                    "name": p["name"],
                    "client": client,
                    "base_url": p["base_url"]
                })
            except Exception as e:
                self.call_from_thread(self.write_to_tab, "tab-1", f"[yellow]⚠️ Proveedor {p['name']} no disponible: {e}[/yellow]")

        if not self.providers:
            self.call_from_thread(self.update_status, "❌ Sin APIs", "Configura .env", "status-loading")
            self.call_from_thread(self.write_to_tab, "tab-1", "[bold red]Error: No se encontró la clave OPENROUTER_API_KEY ni ANYAPI_API_KEY en .env[/bold red]")
            return

        self.active_provider_name = self.providers[0]["name"]

        try:
            free_models = get_available_free_models()
            best_model = select_best_free_model(free_models)
            if "openrouter/free" not in self.candidates:
                self.candidates = ["openrouter/free", best_model] + [m for m in FALLBACK_FREE_MODELS if m != best_model]

            self.selected_model = self.candidates[0]
            provider_names = " / ".join([p["name"] for p in self.providers])
            self.call_from_thread(self.update_status, f"✅ {self.active_provider_name}", self.selected_model, "status-ok")
            self.call_from_thread(self.write_to_tab, "tab-1", f"[green]Conectado con éxito a la red Multi-API: [bold]{provider_names}[/bold][/green]")
            self.call_from_thread(self.write_to_tab, "tab-1", f"Modelo activo: [bold cyan]{self.selected_model}[/bold cyan] (Failover automático activado)\n")
        except Exception:
            self.selected_model = "openrouter/free"
            self.call_from_thread(self.update_status, "⚠️ Respaldo", self.selected_model, "status-loading")

    def update_status(self, status: str, model: str, css_class: str) -> None:
        self.status_label.update(status)
        self.status_label.set_classes(css_class)
        self.model_label.update(model)
        self.model_label.set_classes(css_class)

    def write_to_tab(self, tab_id: str, text: str) -> None:
        try:
            log_widget = self.query_one(f"#log-{tab_id}", RichLog)
            log_widget.write(text)
        except Exception:
            pass

    async def add_new_conversation_tab(self, title: str = None) -> None:
        self.tab_counter += 1
        tab_id = f"tab-{self.tab_counter}"
        tab_title = title or f"Conversación {self.tab_counter}"
        self.conversations[tab_id] = {
            "title": tab_title,
            "chat_history": []
        }

        header_bar = Horizontal(
            Label(f"💬 {tab_title}", classes="tab-title-text"),
            Button("📋 Copiar", id=f"copy-btn-{tab_id}", classes="copy-tab-btn"),
            Button("❌", id=f"close-btn-{tab_id}", classes="close-tab-btn"),
            classes="tab-header-bar"
        )

        log_widget = RichLog(id=f"log-{tab_id}", highlight=True, markup=True)
        new_pane = TabPane(tab_title, header_bar, log_widget, id=tab_id)

        try:
            tabbed_content = self.query_one(TabbedContent)
            await tabbed_content.add_pane(new_pane)
            tabbed_content.active = tab_id

            log_widget.write(f"[bold green]¡Conversación #{self.tab_counter} iniciada con contexto independiente![/bold green]\n")
            self.auto_save()
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo crear la pestaña: {e}")

    async def remove_conversation_tab(self, tab_id: str) -> None:
        if len(self.conversations) <= 1:
            self.action_log.write("[yellow]⚠️ No puedes cerrar la única pestaña activa.[/yellow]")
            return

        try:
            tabbed_content = self.query_one(TabbedContent)
            if tabbed_content.active == tab_id:
                remaining_tabs = [k for k in self.conversations.keys() if k != tab_id]
                if remaining_tabs:
                    tabbed_content.active = remaining_tabs[-1]

            await tabbed_content.remove_pane(tab_id)
            self.conversations.pop(tab_id, None)
            self.action_log.write(f"🗑️ [red]Pestaña cerrada:[/red] {tab_id}")
            self.auto_save()
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo cerrar la pestaña {tab_id}: {e}")

    async def copy_conversation_to_clipboard(self, tab_id: str) -> None:
        if tab_id not in self.conversations:
            self.action_log.write("[yellow]⚠️ No hay conversación para copiar.[/yellow]")
            return

        history = self.conversations[tab_id]["chat_history"]
        if not history:
            self.action_log.write("[yellow]⚠️ La conversación está vacía.[/yellow]")
            return

        lines = []
        for msg in history:
            role = "Tú" if msg["role"] == "user" else "AVFenix Coder"
            lines.append(f"{role}:\n{msg['content']}\n")
            lines.append("-" * 60 + "\n")

        formatted_text = "".join(lines).strip()

        try:
            self.app.clipboard = formatted_text
            self.action_log.write(f"📋 [green]Historial {tab_id} copiado![/green]")
            self.write_to_tab(tab_id, "\n[bold green][Sistema - Portapapeles]: ¡Conversación copiada al portapapeles![/bold green]\n")
        except Exception:
            try:
                import pyperclip
                pyperclip.copy(formatted_text)
                self.action_log.write(f"📋 [green]Copiado con pyperclip ({tab_id})[/green]")
                self.write_to_tab(tab_id, "\n[bold green][Sistema - Portapapeles]: ¡Conversación copiada al portapapeles![/bold green]\n")
            except Exception:
                try:
                    with open("chat_clipboard_backup.txt", "w", encoding="utf-8") as f_backup:
                        f_backup.write(formatted_text)
                    self.action_log.write(f"[⚠️] Guardado en chat_clipboard_backup.txt")
                    self.write_to_tab(tab_id, f"\n[bold yellow][Sistema]: Copia guardada en disco: 'chat_clipboard_backup.txt'[/bold yellow]\n")
                except Exception:
                    self.action_log.write("[Error] No se pudo copiar el historial.")

    async def on_chat_input_submitted(self, event: ChatInput.Submitted) -> None:
        await self.process_user_message()

    async def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "send-btn":
            await self.process_user_message()
        elif event.button.id == "new-tab-btn":
            await self.add_new_conversation_tab()
        elif event.button.id and event.button.id.startswith("close-btn-"):
            tab_id = event.button.id.replace("close-btn-", "")
            await self.remove_conversation_tab(tab_id)
        elif event.button.id and event.button.id.startswith("copy-btn-"):
            tab_id = event.button.id.replace("copy-btn-", "")
            await self.copy_conversation_to_clipboard(tab_id)

    async def action_new_tab(self) -> None:
        await self.add_new_conversation_tab()

    def get_current_active_tab_id(self) -> str:
        try:
            tabbed_content = self.query_one(TabbedContent)
            active_tab = tabbed_content.active
            if active_tab and active_tab in self.conversations:
                return active_tab
        except Exception:
            pass
        return list(self.conversations.keys())[0] if self.conversations else "tab-1"

    async def process_user_message(self) -> None:
        prompt = self.user_input.value.strip()
        if not prompt:
            return

        # Registrar en historial del ChatInput
        if prompt not in self.user_input.prompt_history:
            self.user_input.prompt_history.append(prompt)
        self.user_input.prompt_history_index = len(self.user_input.prompt_history)

        active_tab = self.get_current_active_tab_id()
        self.user_input.value = ""
        self.write_to_tab(active_tab, f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
        self.user_input.disabled = True

        self.run_agent_loop(active_tab, prompt)

    @work(thread=True)
    def run_agent_loop(self, tab_id: str, user_prompt: str) -> None:
        """
        Bucle de ejecución autónomo (Agent Loop) por pestaña con contexto aislado,
        Failover Multi-API y ejecutor universal de herramientas XML.
        """
        if not self.providers:
            self.call_from_thread(self.write_to_tab, tab_id, "[red]Error: Ningún proveedor de API está conectado.[/red]")
            self.call_from_thread(self.enable_input)
            return

        if tab_id not in self.conversations:
            self.conversations[tab_id] = {"title": f"Conversación {tab_id}", "chat_history": []}

        tab_history = self.conversations[tab_id]["chat_history"]
        tab_history.append({"role": "user", "content": user_prompt})

        loop_active = True
        max_iterations = 5
        iteration = 0

        while loop_active and iteration < max_iterations:
            iteration += 1
            self.call_from_thread(self.write_to_tab, tab_id, f"[italic yellow]AVFenix está procesando (Paso {iteration})...[/italic yellow]")

            # Ventana deslizante de memoria
            recent_history = tab_history[-12:]
            messages = [{"role": "system", "content": SYSTEM_PROMPT}] + recent_history

            response_text = ""
            success = False

            # Recorrer proveedores y candidatos con Failover
            for provider_info in self.providers:
                provider_name = provider_info["name"]
                client = provider_info["client"]

                for current_model in self.candidates:
                    try:
                        response = client.chat.completions.create(
                            model=current_model,
                            messages=messages
                        )
                        response_text = response.choices[0].message.content
                        if response_text:
                            success = True
                            self.active_provider_name = provider_name
                            self.call_from_thread(self.update_status, f"✅ {provider_name}", current_model, "status-ok")
                            break
                    except Exception as e:
                        self.call_from_thread(
                            self.write_to_tab,
                            tab_id,
                            f"[yellow]⚠️ Error en {provider_name} ({current_model}): {e}. Conmutando a siguiente opción...[/yellow]"
                        )

                if success:
                    break

            if not success or not response_text:
                self.call_from_thread(
                    self.write_to_tab,
                    tab_id,
                    "[bold red]❌ Error: Todos los proveedores de API (OpenRouter y AnyAPI) y modelos fallaron.[/bold red]"
                )
                break

            tab_history.append({"role": "assistant", "content": response_text})

            # Texto limpio para mostrar al usuario
            clean_text = re.sub(r"<write_file.*?>.*?</write_file>", "", response_text, flags=re.DOTALL)
            clean_text = re.sub(r"<patch_file.*?>.*?</patch_file>", "", clean_text, flags=re.DOTALL)
            clean_text = re.sub(r"<add_source.*?>.*?</add_source>", "", clean_text, flags=re.DOTALL)
            clean_text = re.sub(r"<read_file.*?/>", "", clean_text)
            clean_text = re.sub(r"<list_directory.*?/>", "", clean_text)
            clean_text = re.sub(r"<make_directory.*?/>", "", clean_text)
            clean_text = re.sub(r"<move_file.*?/>", "", clean_text)
            clean_text = re.sub(r"<delete_file.*?/>", "", clean_text)
            clean_text = re.sub(r"<list_sources.*?/>", "", clean_text)
            clean_text = clean_text.strip()

            if clean_text:
                self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold green]AVFenix Coder ({self.active_provider_name}):[/bold green]\n{clean_text}")

            # Ejecutar herramientas XML parseadas
            tool_feedback = self._execute_parsed_tools(response_text)
            if not tool_feedback:
                loop_active = False
            else:
                feedback_msg = "\n".join(tool_feedback)
                tab_history.append({"role": "user", "content": feedback_msg})

        self.auto_save()
        self.call_from_thread(self.enable_input)

    def _execute_parsed_tools(self, response_text: str) -> List[str]:
        """
        Analizador y ejecutor universal de herramientas XML (Locales, Sistema, Fuentes y Plugins).
        """
        tool_feedback = []

        # 1. list_directory
        for m in re.finditer(r'<list_directory\s+path=["\'](.*?)["\']\s*/>|<list_directory\s*/>', response_text):
            p = m.group(1) if m.group(1) else "."
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]list_directory('{p}')...[/cyan]")
            res = list_directory(p)
            tool_feedback.append(f"[Resultado de herramienta 'list_directory': {res}]")

        # 2. read_file
        for m in re.finditer(r'<read_file\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]read_file('{p}')...[/cyan]")
            res = read_file(p)
            tool_feedback.append(f"[Resultado de herramienta 'read_file': {res}]")

        # 3. write_file
        for m in re.finditer(r'<write_file\s+path=["\'](.*?)["\']\s*>(.*?)</write_file>', response_text, flags=re.DOTALL):
            p, content = m.group(1), m.group(2)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]write_file('{p}')...[/cyan]")
            res = write_file(p, content)
            tool_feedback.append(f"[Resultado de herramienta 'write_file': {res}]")

        # 4. patch_file
        for m in re.finditer(r'<patch_file\s+path=["\'](.*?)["\']\s*>(.*?)</patch_file>', response_text, flags=re.DOTALL):
            p = m.group(1)
            patch_content = m.group(2)
            search_m = re.search(r'<search>(.*?)</search>', patch_content, flags=re.DOTALL)
            replace_m = re.search(r'<replace>(.*?)</replace>', patch_content, flags=re.DOTALL)
            if search_m and replace_m:
                self.call_from_thread(self.action_log.write, f"⚙️ [cyan]patch_file('{p}')...[/cyan]")
                res = patch_file(p, search_m.group(1), replace_m.group(1))
                tool_feedback.append(f"[Resultado de herramienta 'patch_file': {res}]")

        # 5. make_directory
        for m in re.finditer(r'<make_directory\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]make_directory('{p}')...[/cyan]")
            res = make_directory(p)
            tool_feedback.append(f"[Resultado de herramienta 'make_directory': {res}]")

        # 6. move_file
        for m in re.finditer(r'<move_file\s+source=["\'](.*?)["\']\s+destination=["\'](.*?)["\']\s*/>', response_text):
            src, dst = m.group(1), m.group(2)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]move_file('{src}' ➔ '{dst}')...[/cyan]")
            res = move_file(src, dst)
            tool_feedback.append(f"[Resultado de herramienta 'move_file': {res}]")

        # 7. delete_file
        for m in re.finditer(r'<delete_file\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]delete_file('{p}')...[/cyan]")
            res = delete_file(p)
            tool_feedback.append(f"[Resultado de herramienta 'delete_file': {res}]")

        # 8. tree_directory
        for m in re.finditer(r'<tree_directory\s+path=["\'](.*?)["\'](?:\s+max_depth=["\'](.*?)["\'])?\s*/>', response_text):
            p = m.group(1) if m.group(1) else "."
            depth = int(m.group(2)) if m.group(2) else 3
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]tree_directory('{p}')...[/cyan]")
            res = tree_directory(p, depth)
            tool_feedback.append(f"[Resultado de herramienta 'tree_directory':\n{res}]")

        # 9. get_file_info
        for m in re.finditer(r'<get_file_info\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            res = get_file_info(p)
            tool_feedback.append(f"[Resultado de herramienta 'get_file_info':\n{res}]")

        # 10. search_code
        for m in re.finditer(r'<search_code\s+query=["\'](.*?)["\'](?:\s+directory=["\'](.*?)["\'])?\s*/>', response_text):
            q = m.group(1)
            d = m.group(2) if m.group(2) else "."
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]search_code('{q}')...[/cyan]")
            res = search_code(q, d)
            tool_feedback.append(f"[Resultado de herramienta 'search_code':\n{res}]")

        # 11. find_files
        for m in re.finditer(r'<find_files\s+pattern=["\'](.*?)["\'](?:\s+directory=["\'](.*?)["\'])?\s*/>', response_text):
            pat = m.group(1)
            d = m.group(2) if m.group(2) else "."
            res = find_files(pat, d)
            tool_feedback.append(f"[Resultado de herramienta 'find_files':\n{res}]")

        # 12. execute_command
        for m in re.finditer(r'<execute_command(?:\s+timeout=["\'](.*?)["\'])?\s*>(.*?)</execute_command>|<execute_command\s+command=["\'](.*?)["\']\s*/>', response_text, flags=re.DOTALL):
            cmd = m.group(2) if m.group(2) else m.group(3)
            if cmd:
                self.call_from_thread(self.action_log.write, f"⚙️ [cyan]execute_command('{cmd[:30]}...')...[/cyan]")
                res = execute_command(cmd)
                tool_feedback.append(f"[Resultado de herramienta 'execute_command':\n{res}]")

        # 13. run_tests
        for m in re.finditer(r'<run_tests(?:\s+test_path=["\'](.*?)["\'])?\s*/>', response_text):
            tp = m.group(1) if m.group(1) else "."
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]run_tests('{tp}')...[/cyan]")
            res = run_tests(tp)
            tool_feedback.append(f"[Resultado de herramienta 'run_tests':\n{res}]")

        # 14. git_status
        for m in re.finditer(r'<git_status(?:\s+directory=["\'](.*?)["\'])?\s*/>', response_text):
            d = m.group(1) if m.group(1) else "."
            res = git_status(d)
            tool_feedback.append(f"[Resultado de herramienta 'git_status':\n{res}]")

        # 15. create_backup
        for m in re.finditer(r'<create_backup\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            res = create_backup(p)
            tool_feedback.append(f"[Resultado de herramienta 'create_backup': {res}]")

        # 16. add_source
        for m in re.finditer(r'<add_source\s+type=["\'](.*?)["\']\s+path_or_url=["\'](.*?)["\'](?:\s+name=["\'](.*?)["\'])?\s*/>', response_text):
            stype, path_url, name = m.group(1), m.group(2), m.group(3)
            self.call_from_thread(self.action_log.write, f"⚙️ [cyan]add_source('{stype}', '{path_url}')...[/cyan]")
            res = add_source(stype, path_url, name)
            self.call_from_thread(self.refresh_sources_widget)
            tool_feedback.append(f"[Resultado de herramienta 'add_source':\n{res}]")

        # 17. list_sources
        for m in re.finditer(r'<list_sources\s*/>', response_text):
            res = list_sources()
            tool_feedback.append(f"[Resultado de herramienta 'list_sources':\n{res}]")

        # 18. search_sources
        for m in re.finditer(r'<search_sources\s+query=["\'](.*?)["\']\s*/>', response_text):
            q = m.group(1)
            res = search_sources(q)
            tool_feedback.append(f"[Resultado de herramienta 'search_sources':\n{res}]")

        # 19. Herramientas personalizadas de Plugins dinámicos
        if hasattr(self, 'plugin_mgr') and self.plugin_mgr.custom_xml_tools:
            for tag_name, handler in self.plugin_mgr.custom_xml_tools.items():
                pattern = rf'<{tag_name}(?:\s+[^>]*?)?/>|<{tag_name}(?:\s+[^>]*?)?>(.*?)</{tag_name}>'
                for m in re.finditer(pattern, response_text, flags=re.DOTALL):
                    self.call_from_thread(self.action_log.write, f"🔌 [cyan]Plugin Tool: <{tag_name}/>...[/cyan]")
                    try:
                        res = handler()
                        tool_feedback.append(f"[Resultado de herramienta de Plugin '{tag_name}': {res}]")
                    except Exception as e:
                        tool_feedback.append(f"[Error en herramienta de Plugin '{tag_name}': {e}]")

        return tool_feedback

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()
