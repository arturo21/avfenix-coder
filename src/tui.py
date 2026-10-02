# -*- coding: utf-8 -*-
"""
tui.py - Interfaz de Terminal Autónoma (TUI Textual) para AVFenix Coder.
Soporta Sistema Multitabs con conversaciones e historiales aislados, Persistencia Atómica,
Failover Multi-API (OpenRouter ➔ AnyAPI), Inyección de Perfil de Estilo del Usuario,
Navegación AST, Ecosistema MCP, Orquestador Swarm, Flujos Git Avanzados, Sandbox Seguro
y Enriquecimiento Visual TUI (Directorios, Markdown, Tablas de Estado y Diffs).
"""

import os
import sys
import re
import json
import logging
import asyncio
import requests
import openai
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
    delete_file, run_tests, git_status, create_backup, get_file_info, search_code, find_files,
    execute_command, tree_directory, fetch_web_page, add_source, list_sources, search_sources,
    remove_source, learn_user_style, get_user_style_profile, mcp_register_server, mcp_list_servers,
    mcp_unregister_server, install_plugin_package, list_installed_plugins, uninstall_plugin_package,
    ast_index_repository, ast_find_definition, ast_get_file_outline, ast_find_references,
    spawn_subagent, run_swarm_pipeline, get_swarm_status, git_get_diff, git_create_branch,
    git_smart_commit, git_generate_pr_summary, sandbox_execute_command, sandbox_get_policy,
    sandbox_get_audit_log, ui_render_directory_tree, ui_preview_file, ui_render_markdown,
    ui_render_status_table
)
from src.session_manager import SessionManager
from src.user_style_memory import UserStyleMemory

logger = logging.getLogger("AVFenixTUI")


class ChatInput(TextArea):
    """
    Campo de entrada multilínea para la TUI.
    Enter envía el mensaje, Shift+Enter inserta salto de línea, y flechas Arriba/Abajo navegan el historial.
    """
    class Submitted(Message):
        def __init__(self, chat_input: "ChatInput") -> None:
            super().__init__()
            self.chat_input = chat_input
            self.value = chat_input.text

    def __init__(self, **kwargs):
        super().__init__(show_line_numbers=False, **kwargs)
        self.prompt_history = []
        self.prompt_history_index = 0
        self.current_draft = ""

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
        color: #11111b;
    }
    #new-tab-btn {
        margin-top: 1;
        width: 100%;
        background: #a6e3a1;
        color: #11111b;
        border: none;
        text-style: bold;
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
        height: 10;
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
    """

    TITLE = "AVFenix Coder"
    SUBTITLE = "Agente Autónomo de Codificación (v14: TUI Enriquecida, Tree & Markdown)"
    BINDINGS = [
        ("q", "quit", "Salir"),
        ("n", "new_tab", "Nueva Conversación")
    ]

    def __init__(self):
        super().__init__()
        self.selected_model = "openrouter/free"
        self.providers = []
        self.active_provider_name = "Desconectado"
        self.candidates = []
        self.session_mgr = SessionManager()
        self.style_memory = UserStyleMemory()

        self.tab_counter = 1
        self.conversations = {
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
                self.model_label = Label("openrouter/free", classes="status-ok")
                yield self.model_label

                yield Button("🆕 Nueva Conversación", variant="success", id="new-tab-btn")

                yield Label("\n[bold]Historial de Acciones:[/bold]")
                self.action_log = RichLog(classes="history-area", highlight=True, markup=True)
                yield self.action_log
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nEnter envía. Shift+Enter salto. Flechas Arriba/Abajo historial. N nueva pestaña.")

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
                    self.user_input = ChatInput(placeholder="Pídele crear, editar, inspeccionar directorios, markdown o métricas...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    async def on_mount(self) -> None:
        self.initialize_agent()

        session_data = self.session_mgr.load_session()
        if session_data:
            await self.restore_session_state(session_data)
        else:
            self.write_to_tab("tab-1", "[bold green]¡Bienvenido a AVFenix Coder (v14: TUI Enriquecida, Tree & Markdown)![/bold green]\nInicializando entorno autónomo...\n")

    async def restore_session_state(self, data: dict) -> None:
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

            self.action_log.write(f"💾 [green]Sesión restaurada ({len(saved_conversations)} pestañas).[/green]")
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo restaurar sesión: {e}")

    def populate_log_history(self, log_widget: RichLog, history: list) -> None:
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
        self.auto_save()

    @work(thread=True)
    def initialize_agent(self) -> None:
        provider_configs = get_provider_endpoints()
        self.providers = []

        for p in provider_configs:
            try:
                headers = {
                    "HTTP-Referer": "https://avfenix-coder.local",
                    "X-Title": "AVFenix Coder"
                }
                client = openai.OpenAI(
                    base_url=p["base_url"],
                    api_key=p["api_key"],
                    default_headers=headers
                )
                self.providers.append({
                    "name": p["name"],
                    "client": client,
                    "base_url": p["base_url"],
                    "api_key": p["api_key"]
                })
            except Exception as e:
                self.call_from_thread(self.action_log.write, f"[yellow]⚠️ No se pudo inicializar proveedor {p['name']}: {e}[/yellow]")

        if not self.providers:
            self.call_from_thread(self.update_status, "❌ Sin APIs", "Configura .env", "status-loading")
            self.call_from_thread(self.write_to_tab, "tab-1", "[bold red]Error: No se encontró clave de API en tu archivo .env[/bold red]")
            return

        self.active_provider_name = self.providers[0]["name"]

        try:
            free_models = get_available_free_models()
            self.candidates = free_models
            best_model_info = select_best_free_model(free_models)

            if isinstance(best_model_info, dict):
                self.selected_model = best_model_info.get("model", "meta-llama/llama-3.1-8b-instruct:free")
            else:
                self.selected_model = str(best_model_info)

            provider_names = " / ".join([p["name"] for p in self.providers])
            self.call_from_thread(self.update_status, f"✅ {self.active_provider_name}", self.selected_model, "status-ok")
            self.call_from_thread(self.write_to_tab, "tab-1", f"[green]Conectado con éxito a la red de proveedores: [bold]{provider_names}[/bold][/green]")
            self.call_from_thread(self.write_to_tab, "tab-1", f"Modelo gratuito activo: [bold cyan]{self.selected_model}[/bold cyan] (Failover Multi-API habilitado)\n")
        except Exception as e:
            self.selected_model = FALLBACK_FREE_MODELS[0]
            self.call_from_thread(self.update_status, "⚠️ Modo Respaldo", self.selected_model, "status-loading")

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

    def get_current_tab_id(self) -> str:
        try:
            tabbed_content = self.query_one(TabbedContent)
            return str(tabbed_content.active) or "tab-1"
        except Exception:
            return "tab-1"

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

            log_widget.write(f"[bold green]¡Conversación #{self.tab_counter} iniciada con contexto aislado![/bold green]\n")
            self.auto_save()
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo crear pestaña: {e}")

    async def remove_conversation_tab(self, tab_id: str) -> None:
        if len(self.conversations) <= 1:
            self.action_log.write("[yellow]⚠️ No puedes eliminar la única conversación activa.[/yellow]")
            return

        try:
            tabbed_content = self.query_one(TabbedContent)

            if tabbed_content.active == tab_id:
                remaining_tabs = [k for k in self.conversations.keys() if k != tab_id]
                if remaining_tabs:
                    tabbed_content.active = remaining_tabs[-1]

            await tabbed_content.remove_pane(tab_id)
            self.conversations.pop(tab_id, None)
            self.action_log.write(f"🗑️ [red]Conversación cerrada:[/red] {tab_id}")
            self.auto_save()
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo cerrar pestaña {tab_id}: {e}")

    async def copy_conversation_to_clipboard(self, tab_id: str) -> None:
        if tab_id not in self.conversations:
            return

        history = self.conversations[tab_id]["chat_history"]
        if not history:
            return

        lines = []
        for msg in history:
            role = "Tú" if msg["role"] == "user" else "AVFenix Coder"
            lines.append(f"{role}:\n{msg['content']}\n")
            lines.append("-" * 60 + "\n")

        formatted_text = "".join(lines).strip()

        try:
            self.app.clipboard = formatted_text
            self.action_log.write(f"📋 [green]Conversación {tab_id} copiada.[/green]")
        except Exception:
            pass

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

    async def process_user_message(self) -> None:
        prompt = self.user_input.value.strip()
        if not prompt:
            return

        tab_id = self.get_current_tab_id()

        if prompt not in self.user_input.prompt_history:
            self.user_input.prompt_history.append(prompt)
        self.user_input.prompt_history_index = len(self.user_input.prompt_history)

        self.user_input.value = ""
        self.write_to_tab(tab_id, f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
        self.user_input.disabled = True

        self.run_agent_loop(tab_id, prompt)

    @work(thread=True)
    def run_agent_loop(self, tab_id: str, user_prompt: str) -> None:
        if not self.providers:
            self.call_from_thread(self.write_to_tab, tab_id, "[red]Error: Ningún proveedor de API configurado.[/red]")
            self.call_from_thread(self.enable_input)
            return

        if tab_id not in self.conversations:
            self.conversations[tab_id] = {"title": tab_id, "chat_history": []}

        tab_history = self.conversations[tab_id]["chat_history"]
        tab_history.append({"role": "user", "content": user_prompt})

        loop_active = True
        max_iterations = 8
        iteration = 0

        style_prompt_injection = self.style_memory.get_prompt_injection()
        effective_system_prompt = SYSTEM_PROMPT + "\n\n" + style_prompt_injection

        while loop_active and iteration < max_iterations:
            iteration += 1
            self.call_from_thread(self.write_to_tab, tab_id, f"[italic yellow]AVFenix está procesando (Paso {iteration})...[/italic yellow]")

            recent_history = tab_history[-12:]
            messages = [{"role": "system", "content": effective_system_prompt}] + recent_history

            response_text = ""
            success = False

            for provider_info in self.providers:
                provider_name = provider_info["name"]
                client = provider_info["client"]
                api_key = provider_info["api_key"]
                base_url = provider_info["base_url"]

                for cand in self.candidates:
                    current_model = cand.get("model") if isinstance(cand, dict) else str(cand)

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
                        try:
                            headers = {
                                "Authorization": f"Bearer {api_key}",
                                "Content-Type": "application/json",
                                "HTTP-Referer": "https://avfenix-coder.local",
                                "X-Title": "AVFenix Coder"
                            }
                            payload = {"model": current_model, "messages": messages}
                            res = requests.post(f"{base_url}/chat/completions", json=payload, headers=headers, timeout=25)
                            if res.status_code == 200:
                                res_json = res.json()
                                response_text = res_json["choices"][0]["message"]["content"]
                                if response_text:
                                    success = True
                                    self.active_provider_name = provider_name
                                    self.call_from_thread(self.update_status, f"✅ {provider_name}", current_model, "status-ok")
                                    break
                        except Exception:
                            pass

                        self.call_from_thread(
                            self.write_to_tab, tab_id,
                            f"[yellow]⚠️ Reintentando por fallo en {provider_name} ({current_model}): {e}[/yellow]"
                        )

                if success:
                    break

            if not success or not response_text:
                self.call_from_thread(self.write_to_tab, tab_id, "[bold red]❌ Error: Todos los proveedores y modelos fallaron.[/bold red]")
                break

            tab_history.append({"role": "assistant", "content": response_text})

            clean_text = re.sub(r"<write_file.*?>.*?</write_file>", "", response_text, flags=re.DOTALL)
            clean_text = re.sub(r"<patch_file.*?>.*?</patch_file>", "", clean_text, flags=re.DOTALL)
            clean_text = re.sub(r"<.*?>", "", clean_text).strip()

            if clean_text:
                self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold green]AVFenix Coder ({self.active_provider_name}):[/bold green]\n{clean_text}")

            executed_results = self._execute_parsed_tools(response_text)

            if executed_results:
                feedback_msg = "\n".join(executed_results)
                tab_history.append({"role": "user", "content": feedback_msg})
            else:
                loop_active = False

            self.auto_save()

        self.call_from_thread(self.enable_input)

    def _execute_parsed_tools(self, response_text: str) -> List[str]:
        """Parser e intérprete universal de herramientas XML."""
        results = []

        # <ui_render_directory_tree path="..."/>
        for m in re.finditer(r'<ui_render_directory_tree\s+path=["\'](.*?)["\']\s*/>|<ui_render_directory_tree\s*/>', response_text):
            p = m.group(1) if m.group(1) else "."
            res = ui_render_directory_tree(p)
            self.call_from_thread(self.action_log.write, f"🎨 ui_render_directory_tree('{p}')")
            results.append(f"[Resultado 'ui_render_directory_tree':\n{res}]")

        # <ui_preview_file filepath="..." max_lines="..."/>
        for m in re.finditer(r'<ui_preview_file\s+filepath=["\'](.*?)["\'](?:\s+max_lines=["\'](\d+)["\'])?\s*/>', response_text):
            fp = m.group(1)
            ml = int(m.group(2)) if m.group(2) else 150
            res = ui_preview_file(fp, ml)
            self.call_from_thread(self.action_log.write, f"🎨 ui_preview_file('{fp}')")
            results.append(f"[Resultado 'ui_preview_file':\n{res}]")

        # <ui_render_markdown path_or_content="..."/>
        for m in re.finditer(r'<ui_render_markdown\s+path_or_content=["\'](.*?)["\']\s*/>', response_text, flags=re.DOTALL):
            poc = m.group(1)
            res = ui_render_markdown(poc)
            self.call_from_thread(self.action_log.write, f"📝 ui_render_markdown('{poc[:15]}...')")
            results.append(f"[Resultado 'ui_render_markdown':\n{res}]")

        # <ui_render_status_table category="..."/>
        for m in re.finditer(r'<ui_render_status_table\s+category=["\'](.*?)["\']\s*/>|<ui_render_status_table\s*/>', response_text):
            cat = m.group(1) if m.group(1) else "all"
            res = ui_render_status_table(cat)
            self.call_from_thread(self.action_log.write, f"📊 ui_render_status_table('{cat}')")
            results.append(f"[Resultado 'ui_render_status_table':\n{res}]")

        # <sandbox_execute_command command="..." timeout="..."/>
        for m in re.finditer(r'<sandbox_execute_command\s+command=["\'](.*?)["\'](?:\s+timeout=["\'](\d+)["\'])?\s*/>', response_text):
            cmd = m.group(1)
            to = int(m.group(2)) if m.group(2) else 30
            res = sandbox_execute_command(cmd, to)
            self.call_from_thread(self.action_log.write, f"🛡️ sandbox_execute_command('{cmd[:15]}...')")
            results.append(f"[Resultado 'sandbox_execute_command': {res}]")

        # <git_get_diff filepath="..."/>
        for m in re.finditer(r'<git_get_diff\s+filepath=["\'](.*?)["\']\s*/>|<git_get_diff\s*/>', response_text):
            fp = m.group(1) if m.group(1) else ""
            res = git_get_diff(fp)
            self.call_from_thread(self.action_log.write, f"🔀 git_get_diff('{fp}')")
            results.append(f"[Resultado 'git_get_diff': {res}]")

        # <git_create_branch branch_name="..."/>
        for m in re.finditer(r'<git_create_branch\s+branch_name=["\'](.*?)["\']\s*/>', response_text):
            bname = m.group(1)
            res = git_create_branch(bname)
            self.call_from_thread(self.action_log.write, f"🌿 git_create_branch('{bname}')")
            results.append(f"[Resultado 'git_create_branch': {res}]")

        # <git_smart_commit .../>
        if "<git_smart_commit" in response_text:
            m = re.search(r'<git_smart_commit(?:\s+message=["\'](.*?)["\'])?\s*/>', response_text)
            msg = m.group(1) if m and m.group(1) else ""
            res = git_smart_commit(msg)
            self.call_from_thread(self.action_log.write, f"📌 git_smart_commit('{msg}')")
            results.append(f"[Resultado 'git_smart_commit': {res}]")

        # <git_generate_pr_summary .../>
        for m in re.finditer(r'<git_generate_pr_summary\s+base_branch=["\'](.*?)["\']\s*/>|<git_generate_pr_summary\s*/>', response_text):
            base = m.group(1) if m.group(1) else "main"
            res = git_generate_pr_summary(base)
            self.call_from_thread(self.action_log.write, f"🔀 git_generate_pr_summary('{base}')")
            results.append(f"[Resultado 'git_generate_pr_summary': {res}]")

        # <spawn_subagent role="..." task="..."/>
        for m in re.finditer(r'<spawn_subagent\s+role=["\'](.*?)["\']\s+task=["\'](.*?)["\'](?:\s+context=["\'](.*?)["\'])?\s*/>', response_text):
            role, task, ctx = m.group(1), m.group(2), m.group(3) or ""
            res = spawn_subagent(role, task, ctx)
            self.call_from_thread(self.action_log.write, f"🤖 spawn_subagent('{role}', '{task[:15]}...')")
            results.append(f"[Resultado 'spawn_subagent': {res}]")

        # <run_swarm_pipeline task_description="..."/>
        for m in re.finditer(r'<run_swarm_pipeline\s+task_description=["\'](.*?)["\']\s*/>', response_text):
            desc = m.group(1)
            res = run_swarm_pipeline(desc)
            self.call_from_thread(self.action_log.write, f"🚀 run_swarm_pipeline('{desc[:15]}...')")
            results.append(f"[Resultado 'run_swarm_pipeline': {res}]")

        # <get_swarm_status/>
        if "<get_swarm_status/>" in response_text or "<get_swarm_status />" in response_text:
            res = get_swarm_status()
            results.append(f"[Resultado 'get_swarm_status': {res}]")

        # <ast_index_repository directory="..."/>
        for m in re.finditer(r'<ast_index_repository\s+directory=["\'](.*?)["\']\s*/>|<ast_index_repository\s*/>', response_text):
            d = m.group(1) if m.group(1) else "."
            res = ast_index_repository(d)
            results.append(f"[Resultado 'ast_index_repository': {res}]")

        # <ast_find_definition symbol_name="..."/>
        for m in re.finditer(r'<ast_find_definition\s+symbol_name=["\'](.*?)["\']\s*/>', response_text):
            sym = m.group(1)
            res = ast_find_definition(sym)
            results.append(f"[Resultado 'ast_find_definition': {res}]")

        # <ast_get_file_outline filepath="..."/>
        for m in re.finditer(r'<ast_get_file_outline\s+filepath=["\'](.*?)["\']\s*/>', response_text):
            fp = m.group(1)
            res = ast_get_file_outline(fp)
            results.append(f"[Resultado 'ast_get_file_outline': {res}]")

        # <ast_find_references symbol_name="..."/>
        for m in re.finditer(r'<ast_find_references\s+symbol_name=["\'](.*?)["\']\s*/>', response_text):
            sym = m.group(1)
            res = ast_find_references(sym)
            results.append(f"[Resultado 'ast_find_references': {res}]")

        # <list_directory path="..."/>
        for m in re.finditer(r'<list_directory\s+path=["\'](.*?)["\']\s*/>|<list_directory\s*/>', response_text):
            p = m.group(1) if m.group(1) else "."
            res = list_directory(p)
            self.call_from_thread(self.action_log.write, f"⚙️ list_directory('{p}')")
            results.append(f"[Resultado 'list_directory': {res}]")

        # <make_directory path="..."/>
        for m in re.finditer(r'<make_directory\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            res = make_directory(p)
            self.call_from_thread(self.action_log.write, f"⚙️ make_directory('{p}')")
            results.append(f"[Resultado 'make_directory': {res}]")

        # <read_file path="..."/>
        for m in re.finditer(r'<read_file\s+path=["\'](.*?)["\']\s*/>', response_text):
            p = m.group(1)
            res = read_file(p)
            self.call_from_thread(self.action_log.write, f"⚙️ read_file('{p}')")
            results.append(f"[Resultado 'read_file': {res}]")

        # <write_file path="...">content</write_file>
        for m in re.finditer(r'<write_file\s+path=["\'](.*?)["\']\s*>(.*?)</write_file>', response_text, flags=re.DOTALL):
            p, content = m.group(1), m.group(2)
            res = write_file(p, content)
            self.style_memory.analyze_and_learn_from_code(p, content)
            self.call_from_thread(self.action_log.write, f"⚙️ write_file('{p}')")
            results.append(f"[Resultado 'write_file': {res}]")

        # <patch_file path="...">...</patch_file>
        for m in re.finditer(r'<patch_file\s+path=["\'](.*?)[\"\']\s*>(.*?)</patch_file>', response_text, flags=re.DOTALL):
            p, patch_c = m.group(1), m.group(2)
            sm = re.search(r'<search>(.*?)</search>', patch_c, flags=re.DOTALL)
            rm = re.search(r'<replace>(.*?)</replace>', patch_c, flags=re.DOTALL)
            if sm and rm:
                res = patch_file(p, sm.group(1), rm.group(1))
                results.append(f"[Resultado 'patch_file': {res}]")

        # <execute_command command="..."/>
        for m in re.finditer(r'<execute_command\s+command=["\'](.*?)["\']\s*/>|<execute_command.*?>(.*?)</execute_command>', response_text, flags=re.DOTALL):
            cmd = m.group(1) or m.group(2)
            if cmd:
                res = execute_command(cmd)
                self.call_from_thread(self.action_log.write, f"⚙️ execute_command('{cmd[:20]}...')")
                results.append(f"[Resultado 'execute_command': {res}]")

        # <run_tests test_path="..."/>
        for m in re.finditer(r'<run_tests\s+test_path=["\'](.*?)["\']\s*/>|<run_tests\s*/>', response_text):
            tp = m.group(1) if m.group(1) else "."
            res = run_tests(tp)
            self.call_from_thread(self.action_log.write, f"⚙️ run_tests('{tp}')")
            results.append(f"[Resultado 'run_tests': {res}]")

        # <add_source .../>
        for m in re.finditer(r'<add_source\s+type=["\'](.*?)["\']\s+path_or_url=["\'](.*?)["\'](?:\s+name=["\'](.*?)["\'])?\s*/>', response_text):
            t_name, p_url, name = m.group(1), m.group(2), m.group(3)
            res = add_source(t_name, p_url, name)
            results.append(f"[Resultado 'add_source': {res}]")

        # <search_sources query="..."/>
        for m in re.finditer(r'<search_sources\s+query=["\'](.*?)["\']\s*/>', response_text):
            q = m.group(1)
            res = search_sources(q)
            results.append(f"[Resultado 'search_sources': {res}]")

        # <mcp_register_server .../>
        for m in re.finditer(r'<mcp_register_server\s+server_id=["\'](.*?)["\']\s+name=["\'](.*?)["\']\s+command=["\'](.*?)["\'](?:\s+args=["\'](.*?)["\'])?\s*/>', response_text):
            sid, name, cmd, args = m.group(1), m.group(2), m.group(3), m.group(4) or ""
            res = mcp_register_server(sid, name, cmd, args)
            results.append(f"[Resultado 'mcp_register_server': {res}]")

        # <mcp_list_servers/>
        if "<mcp_list_servers/>" in response_text or "<mcp_list_servers />" in response_text:
            res = mcp_list_servers()
            results.append(f"[Resultado 'mcp_list_servers': {res}]")

        # <learn_user_style pattern="..."/>
        for m in re.finditer(r'<learn_user_style\s+pattern=["\'](.*?)["\']\s*/>', response_text):
            pat = m.group(1)
            res = learn_user_style(pat)
            results.append(f"[Resultado 'learn_user_style': {res}]")

        return results

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()
