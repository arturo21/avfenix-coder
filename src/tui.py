# -*- coding: utf-8 -*-
import openai
import re
from rich.markup import escape
from textual.app import App, ComposeResult
from textual.containers import Vertical, Horizontal
from textual.widgets import Header, Footer, RichLog, Button, Label, TabbedContent, TabPane, TextArea
from textual import work, events
from textual.message import Message
from src.config import (
    OPENROUTER_API_KEY, ANYAPI_API_KEY, get_multi_provider_free_candidates, FALLBACK_FREE_MODELS
)
from src.prompts import SYSTEM_PROMPT
from src.tools import (
    read_file, write_file, patch_file, make_directory, list_directory, move_file,
    execute_command, search_code, find_files, delete_file, run_tests, git_status,
    create_backup, get_file_info, fetch_web_page, tree_directory,
    set_working_dir, add_context
)
from src.session_manager import SessionManager

class ChatInput(TextArea):
    """
    Campo de entrada de texto multilínea optimizado para chat.
    Presionar Enter envía el mensaje, mientras que Shift+Enter inserta un salto de línea.
    Además, incluye un historial de prompts navegable con las flechas Arriba y Abajo.
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
        height: 12;
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
    SUBTITLE = "Agente Autónomo de Codificación (Incansable & Multi-Proveedor - v12)"
    BINDINGS = [
        ("q", "quit", "Salir"),
        ("n", "new_tab", "Nueva Conversación")
    ]

    def __init__(self):
        super().__init__()
        self.selected_model = "Buscando..."
        self.candidates = []
        self.session_mgr = SessionManager()
        
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
                self.model_label = Label("Cargando...", classes="status-loading")
                yield self.model_label
                
                yield Button("🆕 Nueva Conversación", variant="success", id="new-tab-btn")
                
                yield Label("\n[bold]Historial de Acciones:[/bold]")
                self.action_log = RichLog(classes="history-area", highlight=True, markup=True)
                yield self.action_log
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nPresiona Enter para enviar. Shift+Enter para salto de línea. Bucle incansable activo.")
            
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
                    self.user_input = ChatInput(placeholder="Asígname un objetivo, directorio o tarea...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    async def on_mount(self) -> None:
        self.initialize_agent()
        
        session_data = self.session_mgr.load_session()
        if session_data:
            await self.restore_session_state(session_data)
        else:
            self.write_to_tab("tab-1", "[bold green]¡Bienvenido a AVFenix Coder![/bold green]\nInicializando motor asíncrono incansable multi-proveedor...\n")

    async def restore_session_state(self, data: dict) -> None:
        try:
            self.tab_counter = data.get("tab_counter", 1)
            self.user_input.prompt_history = data.get("prompt_history", [])
            self.user_input.prompt_history_index = len(self.user_input.prompt_history)
            
            saved_conversations = data.get("conversations", {})
            if not isinstance(saved_conversations, dict) or not saved_conversations:
                return

            self.conversations = saved_conversations
            tabbed_content = self.query_one(TabbedContent)
            
            first_tab = True
            for tab_id, tab_info in saved_conversations.items():
                if not isinstance(tab_info, dict):
                    continue
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
            self.action_log.write(f"[Error] No se pudo restaurar la sesión: {e}")

    def populate_log_history(self, log_widget: RichLog, history: list) -> None:
        log_widget.write("[bold gray][Sesión Restaurada][/bold gray]")
        if not isinstance(history, list):
            return
        for msg in history:
            if not isinstance(msg, dict):
                continue
            role = msg.get("role")
            content = escape(str(msg.get("content", "")))
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
        if not OPENROUTER_API_KEY and not ANYAPI_API_KEY:
            self.call_from_thread(self.update_status, "❌ Falta .env", "Configura .env", "status-loading")
            self.call_from_thread(self.write_to_tab, "tab-1", "[bold red]Error: No se encontró OPENROUTER_API_KEY ni ANYAPI_API_KEY en tu .env[/bold red]")
            return

        try:
            candidates = get_multi_provider_free_candidates()
            self.candidates = candidates
            if candidates:
                best = candidates[0]
                model_display = f"{best['model']} ({best['provider']})"
                self.selected_model = model_display
                self.call_from_thread(self.update_status, "✅ Conectado", model_display, "status-ok")
                self.call_from_thread(self.write_to_tab, "tab-1", f"[green]Conectado con éxito. Modelo activo: [bold cyan]{model_display}[/bold cyan][/green]\n")
            else:
                self.call_from_thread(self.update_status, "⚠️ Sin modelos", "Fallback active", "status-loading")
        except Exception as e:
            self.call_from_thread(self.update_status, "⚠️ Error init", str(e), "status-loading")

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
            
            log_widget.write(f"[bold green]¡Sesión #{self.tab_counter} iniciada![/bold green]\n")
            self.auto_save()
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo crear la pestaña: {e}")

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
            self.action_log.write(f"[Error] No se pudo cerrar la pestaña {tab_id}: {e}")

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
            self.action_log.write(f"📋 [green]Historial {tab_id} copiado![/green]")
            self.write_to_tab(tab_id, "\n[bold green][Sistema - Portapapeles]: ¡Conversación copiada al portapapeles![/bold green]\n")
        except Exception:
            try:
                import pyperclip
                pyperclip.copy(formatted_text)
                self.write_to_tab(tab_id, "\n[bold green][Sistema]: Copiado al portapapeles![/bold green]\n")
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

        if not self.user_input.prompt_history or self.user_input.prompt_history[-1] != prompt:
            self.user_input.prompt_history.append(prompt)
        self.user_input.prompt_history_index = len(self.user_input.prompt_history)
        self.user_input.current_draft = ""

        try:
            tabbed_content = self.query_one(TabbedContent)
            active_tab_id = tabbed_content.active
        except Exception:
            return

        self.user_input.value = ""
        
        escaped_prompt = escape(prompt)
        self.write_to_tab(active_tab_id, f"\n[bold cyan]Tú:[/bold cyan] {escaped_prompt}")
        self.user_input.disabled = True
        
        self.auto_save()
        self.run_agent_loop(prompt, active_tab_id)

    @work(thread=True)
    def run_agent_loop(self, user_prompt: str, tab_id: str) -> None:
        """Bucle autónomo incansable de ejecución y auto-corrección."""
        if tab_id not in self.conversations:
            self.conversations[tab_id] = {"title": f"Conversación {tab_id}", "chat_history": []}
            
        history = self.conversations[tab_id]["chat_history"]
        history.append({"role": "user", "content": user_prompt})
        
        max_iterations = 12
        current_iteration = 0
        
        while current_iteration < max_iterations:
            current_iteration += 1
            self.call_from_thread(self.write_to_tab, tab_id, f"[italic yellow]🦅 Procesando objetivo (Paso {current_iteration})...[/italic yellow]")
            
            success = False
            response_text = ""
            active_model_name = ""
            
            # Reintentos con failover multi-proveedor
            for cand in self.candidates:
                try:
                    client = openai.OpenAI(
                        base_url=cand["base_url"],
                        api_key=cand["api_key"]
                    )
                    response = client.chat.completions.create(
                        model=cand["model"],
                        messages=[{"role": "system", "content": SYSTEM_PROMPT}] + history
                    )
                    response_text = response.choices[0].message.content or ""
                    active_model_name = f"{cand['model']} ({cand['provider']})"
                    success = True
                    break
                except Exception as e:
                    pass
            
            if not success:
                self.call_from_thread(self.write_to_tab, tab_id, "[bold red]❌ Error: Ningún proveedor de IA gratuito pudo procesar tu solicitud.[/bold red]")
                break

            escaped_resp = escape(response_text)
            self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold green]AVFenix Coder ({active_model_name}):[/bold green]\n{escaped_resp}")
            
            history.append({"role": "assistant", "content": response_text})

            # Analizar y ejecutar herramienta XML
            tool_executed, tool_result = self.parse_and_execute_xml_tool(response_text)
            
            if tool_executed:
                escaped_result = escape(tool_result)
                self.call_from_thread(self.action_log.write, f"⚙️ {tool_result.split(':')[0]}")
                self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold gray][Sistema - Resultado de Herramienta]:[/bold gray]\n{escaped_result}")
                
                # Inyectar el resultado de vuelta en el historial para que el agente verifique o corrija
                history.append({"role": "user", "content": f"[Resultado de herramienta local]:\n{tool_result}"})
            else:
                # Si el modelo no invocó ninguna herramienta, damos por alcanzado el objetivo
                break
                
        self.call_from_thread(self.auto_save)
        self.call_from_thread(self.enable_input)

    def parse_and_execute_xml_tool(self, text: str) -> tuple[bool, str]:
        if not text or not isinstance(text, str):
            return False, ""

        # 1. <set_working_dir/>
        m = re.search(r'<set_working_dir\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, set_working_dir(m.group(1))

        # 2. <add_context/>
        m = re.search(r'<add_context\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, add_context(m.group(1))

        # 3. <list_directory/>
        m = re.search(r'<list_directory(?:\s+path=[\"\'](.*?)[\"\'])?\s*/>', text)
        if m:
            path = m.group(1) if m.group(1) else "."
            return True, list_directory(path)

        # 4. <make_directory/>
        m = re.search(r'<make_directory\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, make_directory(m.group(1))

        # 5. <read_file/>
        m = re.search(r'<read_file\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, read_file(m.group(1))

        # 6. <write_file>content</write_file>
        m = re.search(r'<write_file\s+path=[\"\'](.*?)[\"\']\s*>(.*?)</write_file>', text, re.DOTALL)
        if m:
            return True, write_file(m.group(1), m.group(2))

        # 7. <patch_file>...<search>...<replace>...</patch_file>
        m = re.search(r'<patch_file\s+path=[\"\'](.*?)[\"\']\s*>(.*?)</patch_file>', text, re.DOTALL)
        if m:
            path, raw_patch = m.group(1), m.group(2)
            search_match = re.search(r'<search>(.*?)</search>', raw_patch, re.DOTALL)
            replace_match = re.search(r'<replace>(.*?)</replace>', raw_patch, re.DOTALL)
            if search_match and replace_match:
                return True, patch_file(path, search_match.group(1), replace_match.group(1))
            return True, "[Error] Formato de patch_file incorrecto. Debe contener <search> y <replace>."

        # 8. <move_file/>
        m = re.search(r'<move_file\s+source=[\"\'](.*?)[\"\']\s+destination=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, move_file(m.group(1), m.group(2))

        # 9. <delete_file/>
        m = re.search(r'<delete_file\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, delete_file(m.group(1))

        # 10. <tree_directory/>
        m = re.search(r'<tree_directory(?:\s+path=[\"\'](.*?)[\"\'])?(?:\s+max_depth=[\"\'](\d+)[\"\'])?\s*/>', text)
        if m:
            path = m.group(1) if m.group(1) else "."
            max_depth = int(m.group(2)) if m.group(2) else 3
            return True, tree_directory(path, max_depth)

        # 11. <get_file_info/>
        m = re.search(r'<get_file_info\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, get_file_info(m.group(1))

        # 12. <search_code/>
        m = re.search(r'<search_code\s+query=[\"\'](.*?)[\"\'](?:\s+directory=[\"\'](.*?)[\"\'])?\s*/>', text)
        if m:
            query = m.group(1)
            directory = m.group(2) if m.group(2) else "."
            return True, search_code(query, directory)

        # 13. <find_files/>
        m = re.search(r'<find_files\s+pattern=[\"\'](.*?)[\"\'](?:\s+directory=[\"\'](.*?)[\"\'])?\s*/>', text)
        if m:
            pattern = m.group(1)
            directory = m.group(2) if m.group(2) else "."
            return True, find_files(pattern, directory)

        # 14. <execute_command>cmd</execute_command> o <execute_command command="cmd"/>
        m = re.search(r'<execute_command(?:\s+timeout=[\"\'](\d+)[\"\'])?\s*>(.*?)</execute_command>', text, re.DOTALL)
        if m:
            timeout = int(m.group(1)) if m.group(1) else 30
            command = m.group(2).strip()
            return True, execute_command(command, timeout)
        m2 = re.search(r'<execute_command\s+command=[\"\'](.*?)[\"\'](?:\s+timeout=[\"\'](\d+)[\"\'])?\s*/>', text)
        if m2:
            command = m2.group(1)
            timeout = int(m2.group(2)) if m2.group(2) else 30
            return True, execute_command(command, timeout)

        # 15. <run_tests/>
        m = re.search(r'<run_tests(?:\s+test_path=[\"\'](.*?)[\"\'])?\s*/>', text)
        if m:
            test_path = m.group(1) if m.group(1) else "."
            return True, run_tests(test_path)

        # 16. <git_status/>
        m = re.search(r'<git_status(?:\s+directory=[\"\'](.*?)[\"\'])?\s*/>', text)
        if m:
            directory = m.group(1) if m.group(1) else "."
            return True, git_status(directory)

        # 17. <create_backup/>
        m = re.search(r'<create_backup\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, create_backup(m.group(1))

        # 18. <fetch_web_page/>
        m = re.search(r'<fetch_web_page\s+url=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            return True, fetch_web_page(m.group(1))

        return False, ""

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()