# -*- coding: utf-8 -*-
import openai
import re
from textual.app import App, ComposeResult
from textual.containers import Vertical, Horizontal
from textual.widgets import Header, Footer, RichLog, Button, Label, TabbedContent, TabPane, TextArea
from textual import work, events
from textual.message import Message
from src.config import OPENROUTER_API_KEY, get_available_free_models, select_best_free_model, FALLBACK_FREE_MODELS
from src.prompts import SYSTEM_PROMPT
from src.tools import read_file, write_file, patch_file, make_directory, list_directory, move_file

class ChatInput(TextArea):
    """
    Campo de entrada de texto multilínea optimizado para chat.
    Presionar Enter envía el mensaje, mientras que Shift+Enter inserta un salto de línea.
    Además, incluye un historial de prompts navegable con las flechas Arriba y Abajo.
    """
    class Submitted(Message):
        """Mensaje que se emite al presionar Enter."""
        def __init__(self, chat_input: "ChatInput") -> None:
            super().__init__()
            self.chat_input = chat_input
            self.value = chat_input.text

    def __init__(self, **kwargs):
        # Desactivar números de línea por defecto para la estética de chat
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
            # Si el cursor está en la primera línea, navegamos al historial anterior
            if self.cursor_location[0] == 0:
                if self.prompt_history:
                    event.prevent_default()
                    event.stop()
                    # Si estamos en la entrada actual escribiendo, guardamos el borrador
                    if self.prompt_history_index == len(self.prompt_history):
                        self.current_draft = self.text
                    
                    if self.prompt_history_index > 0:
                        self.prompt_history_index -= 1
                        self.text = self.prompt_history[self.prompt_history_index]
                        # Colocar cursor al final
                        lines = self.text.split("\n")
                        self.cursor_location = (len(lines) - 1, len(lines[-1]))
        elif event.key == "down":
            # Si el cursor está en la última línea, navegamos al historial posterior
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
                    # Colocar cursor al final
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
    
    /* Estilos para el encabezado de pestaña y su botón de cerrar */
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
        background: #fab387; /* Pastel orange / peach tint */
        color: #11111b;
        border: none;
        text-style: bold;
        width: 11;
        height: 1;
        margin-top: 1;
        margin-right: 1;
    }
    .copy-tab-btn:hover {
        background: #f9e2af; /* pastel yellow */
        color: #11111b;
    }
    """

    TITLE = "AVFenix Coder"
    SUBTITLE = "Agente Autónomo de Codificación (Multi-Conversación - v6)"
    BINDINGS = [
        ("q", "quit", "Salir"),
        ("n", "new_tab", "Nueva Conversación")
    ]

    def __init__(self):
        super().__init__()
        self.selected_model = "Buscando..."
        self.client = None
        self.candidates = FALLBACK_FREE_MODELS
        
        # Historial de conversaciones indexado por la ID de la pestaña
        self.tab_counter = 1
        self.conversations = {
            "tab-1": {"chat_history": []}
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
                
                # Botón interactivo para añadir pestañas de conversación en caliente
                yield Button("🆕 Nueva Conversación", variant="success", id="new-tab-btn")
                
                yield Label("\n[bold]Historial de Acciones:[/bold]")
                self.action_log = RichLog(classes="history-area", highlight=True, markup=True)
                yield self.action_log
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nPresiona Enter en el chat o haz clic en Enviar para interactuar. Usa Flecha Arriba/Abajo para navegar por el historial de prompts.")
            
            with Vertical(id="chat-container"):
                # Contenedor dinámico de pestañas nativo de Textual
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
                    self.user_input = ChatInput(placeholder="Pídele crear, editar o analizar archivos en esta conversación...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    def on_mount(self) -> None:
        self.initialize_agent()
        # Saludo inicial en la primera pestaña de conversación por defecto
        self.write_to_tab("tab-1", "[bold green]¡Bienvenido a la interfaz gráfica multi-conversación de AVFenix Coder![/bold green]\nInicializando entorno asíncrono con OpenRouter...\n")

    @work(thread=True)
    def initialize_agent(self) -> None:
        if not OPENROUTER_API_KEY:
            self.call_from_thread(self.update_status, "❌ Falta .env", "Configura .env", "status-loading")
            self.call_from_thread(self.write_to_tab, "tab-1", "[bold red]Error: No se encontró la clave en tu .env[/bold red]")
            return

        self.client = openai.OpenAI(
            base_url="https://openrouter.ai/api/v1",
            api_key=OPENROUTER_API_KEY,
        )

        try:
            free_models = get_available_free_models()
            best_model = select_best_free_model(free_models)
            self.selected_model = best_model
            self.candidates = [best_model] + [m for m in FALLBACK_FREE_MODELS if m != best_model]
            
            self.call_from_thread(self.update_status, "✅ Conectado", best_model, "status-ok")
            self.call_from_thread(self.write_to_tab, "tab-1", f"[green]Conectado con éxito a OpenRouter.[/green]")
            self.call_from_thread(self.write_to_tab, "tab-1", f"Modelo autónomo activo: [bold cyan]{best_model}[/bold cyan]\n")
        except Exception as e:
            self.selected_model = FALLBACK_FREE_MODELS[0]
            self.call_from_thread(self.update_status, "⚠️ Modo Respaldo", self.selected_model, "status-loading")

    def update_status(self, status: str, model: str, css_class: str) -> None:
        self.status_label.update(status)
        self.status_label.set_classes(css_class)
        self.model_label.update(model)
        self.model_label.set_classes(css_class)

    def write_to_tab(self, tab_id: str, text: str) -> None:
        """Escribe texto de forma segura en la consola de una pestaña activa."""
        try:
            log_widget = self.query_one(f"#log-{tab_id}", RichLog)
            log_widget.write(text)
        except Exception:
            pass

    async def add_new_conversation_tab(self, title: str = None) -> None:
        """Agrega dinámicamente un nuevo TabPane de conversación con su respectiva memoria."""
        self.tab_counter += 1
        tab_id = f"tab-{self.tab_counter}"
        self.conversations[tab_id] = {"chat_history": []}
        
        tab_title = title or f"Conversación {self.tab_counter}"
        
        # Crear la barra de encabezado para la pestaña con botón de copiar y cerrar independiente
        header_bar = Horizontal(
            Label(f"💬 {tab_title}", classes="tab-title-text"),
            Button("📋 Copiar", id=f"copy-btn-{tab_id}", classes="copy-tab-btn"),
            Button("❌", id=f"close-btn-{tab_id}", classes="close-tab-btn"),
            classes="tab-header-bar"
        )
        
        # Crear dinámicamente el widget log para esta pestaña
        log_widget = RichLog(id=f"log-{tab_id}", highlight=True, markup=True)
        new_pane = TabPane(tab_title, header_bar, log_widget, id=tab_id)
        
        try:
            tabbed_content = self.query_one(TabbedContent)
            await tabbed_content.add_pane(new_pane)
            tabbed_content.active = tab_id
            
            # Saludo de arranque de la nueva sesión
            log_widget.write(f"[bold green]¡Sesión de conversación #{self.tab_counter} iniciada de forma aislada![/bold green]\nLas herramientas y archivos modificados siguen siendo accesibles, pero la memoria de chat de esta pestaña es completamente independiente.\n")
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo crear la pestaña: {e}")

    async def remove_conversation_tab(self, tab_id: str) -> None:
        """Elimina de forma segura una pestaña de conversación y su memoria asociada."""
        if len(self.conversations) <= 1:
            self.action_log.write("[yellow]⚠️ No puedes eliminar la única conversación activa.[/yellow]")
            return
            
        try:
            tabbed_content = self.query_one(TabbedContent)
            
            # Si cerramos la pestaña activa, cambiamos el foco a otra antes de removerla
            if tabbed_content.active == tab_id:
                remaining_tabs = [k for k in self.conversations.keys() if k != tab_id]
                if remaining_tabs:
                    tabbed_content.active = remaining_tabs[-1]
            
            # Eliminar la pestaña de la TUI
            await tabbed_content.remove_pane(tab_id)
            
            # Eliminar el historial de conversación asociado
            self.conversations.pop(tab_id, None)
            
            self.action_log.write(f"🗑️ [red]Conversación cerrada:[/red] {tab_id}")
        except Exception as e:
            self.action_log.write(f"[Error] No se pudo cerrar la pestaña {tab_id}: {e}")

    async def copy_conversation_to_clipboard(self, tab_id: str) -> None:
        """Copia el historial de la conversación seleccionada al portapapeles de forma limpia."""
        if tab_id not in self.conversations:
            self.action_log.write("[yellow]⚠️ No hay conversación para copiar.[/yellow]")
            return
            
        history = self.conversations[tab_id]["chat_history"]
        if not history:
            self.action_log.write("[yellow]⚠️ La conversación está vacía.[/yellow]")
            return
            
        # Generar un formato legible, limpio y libre de metadatos o tags de sistema
        lines = []
        for msg in history:
            role = "Tú" if msg["role"] == "user" else "AVFenix Coder"
            lines.append(f"{role}:\n{msg['content']}\n")
            lines.append("-" * 60 + "\n")
            
        formatted_text = "".join(lines).strip()
        
        try:
            # Copiar nativamente al portapapeles usando Textual App
            self.app.clipboard = formatted_text
            self.action_log.write(f"📋 [green]Historial {tab_id} copiado![/green]")
            self.write_to_tab(tab_id, "\n[bold green][Sistema - Portapapeles]: ¡Conversación copiada con éxito al portapapeles de tu sistema![/bold green]\n")
        except Exception as e:
            # Fallback secundario con pyperclip si estuviera instalado
            try:
                import pyperclip
                pyperclip.copy(formatted_text)
                self.action_log.write(f"📋 [green]Copiado con pyperclip ({tab_id})[/green]")
                self.write_to_tab(tab_id, "\n[bold green][Sistema - Portapapeles]: ¡Conversación copiada al portapapeles![/bold green]\n")
            except Exception as e2:
                # Fallback de rescate: lo guardamos en un archivo físico para que el usuario no pierda el contenido
                try:
                    with open("chat_clipboard_backup.txt", "w", encoding="utf-8") as f_backup:
                        f_backup.write(formatted_text)
                    self.action_log.write(f"[⚠️] Guardado en chat_clipboard_backup.txt")
                    self.write_to_tab(tab_id, f"\n[bold yellow][Sistema]: No se pudo acceder al portapapeles ({e2}). Guardamos una copia en tu disco: 'chat_clipboard_backup.txt'[/bold yellow]\n")
                except Exception:
                    self.action_log.write("[Error] No se pudo copiar ni guardar historial.")

    # =========================================================================
    # 🔄 EVENTOS ASÍNCRONOS DE LA TUI (MANEJO ROBUSTO POR NOMBRE)
    # =========================================================================

    async def on_chat_input_submitted(self, event: ChatInput.Submitted) -> None:
        await self.process_user_message()

    async def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "send-btn":
            await self.process_user_message()
        elif event.button.id == "new-tab-btn":
            await self.add_new_conversation_tab()
        elif event.button.id and event.button.id.startswith("close-btn-"):
            # Extraer el tab_id de close-btn-{tab_id}
            tab_id = event.button.id.replace("close-btn-", "")
            await self.remove_conversation_tab(tab_id)
        elif event.button.id and event.button.id.startswith("copy-btn-"):
            # Extraer el tab_id de copy-btn-{tab_id}
            tab_id = event.button.id.replace("copy-btn-", "")
            await self.copy_conversation_to_clipboard(tab_id)

    async def action_new_tab(self) -> None:
        """Atajo de teclado 'N' para iniciar una nueva conversación."""
        await self.add_new_conversation_tab()

    async def process_user_message(self) -> None:
        prompt = self.user_input.value.strip()
        if not prompt:
            return

        # Sincronizar historial de comandos unificado de la caja de texto
        if not self.user_input.prompt_history or self.user_input.prompt_history[-1] != prompt:
            self.user_input.prompt_history.append(prompt)
        self.user_input.prompt_history_index = len(self.user_input.prompt_history)
        self.user_input.current_draft = ""

        try:
            tabbed_content = self.query_one(TabbedContent)
            active_tab_id = tabbed_content.active
        except Exception:
            # Fallback si no hay pestañas activas
            return

        self.user_input.value = ""
        
        # Registrar el mensaje del usuario en el log activo
        self.write_to_tab(active_tab_id, f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
        self.user_input.disabled = True
        
        # Ejecutar el bucle del agente asíncronamente para la pestaña seleccionada
        self.run_agent_loop(prompt, active_tab_id)

    # =========================================================================

    @work(thread=True)
    def run_agent_loop(self, user_prompt: str, tab_id: str) -> None:
        """Bucle de ejecución autónomo (Agent Loop) con memoria de historial aislada por pestaña."""
        if not self.client:
            self.call_from_thread(self.write_to_tab, tab_id, "[red]Error: API Key ausente o no inicializada.[/red]")
            self.call_from_thread(self.enable_input)
            return

        # Sincronizar memoria de conversación correspondiente al id de la pestaña
        if tab_id not in self.conversations:
            self.conversations[tab_id] = {"chat_history": []}
            
        history = self.conversations[tab_id]["chat_history"]
        history.append({"role": "user", "content": user_prompt})
        
        max_iterations = 8
        current_iteration = 0
        
        while current_iteration < max_iterations:
            current_iteration += 1
            self.call_from_thread(self.write_to_tab, tab_id, f"[italic yellow]🦅 Analizando archivos y procesando paso {current_iteration}...[/italic yellow]")
            
            success = False
            response_text = ""
            active_model = ""
            
            # Reintentos automáticos para resiliencia total
            for current_model in self.candidates:
                try:
                    response = self.client.chat.completions.create(
                        model=current_model,
                        messages=[{"role": "system", "content": SYSTEM_PROMPT}] + history
                    )
                    # Indexado de lista de opciones corregido para evitar AttributeErrors
                    response_text = response.choices[0].message.content
                    active_model = current_model
                    success = True
                    break
                except Exception as e:
                    self.call_from_thread(self.write_to_tab, tab_id, f"[yellow]⚠️ Fallo con {current_model}: {e}. Intentando respaldo...[/yellow]")
            
            if not success:
                self.call_from_thread(self.write_to_tab, tab_id, "[bold red]❌ Error crítico: Ningún modelo gratuito pudo procesar tu solicitud.[/bold red]")
                break

            # Mostrar respuesta del agente en el log activo
            self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold green]AVFenix Coder ({active_model}):[/bold green]")
            self.call_from_thread(self.write_to_tab, tab_id, response_text)
            
            # Guardar en memoria la respuesta de la IA
            history.append({"role": "assistant", "content": response_text})

            # Analizar si la IA quiere ejecutar alguna herramienta XML
            tool_executed, tool_result = self.parse_and_execute_xml_tool(response_text)
            
            if tool_executed:
                # Escribir la acción en la barra lateral del sistema de archivos
                self.call_from_thread(self.action_log.write, f"⚙️ {tool_result.split(':')[0]}")
                self.call_from_thread(self.write_to_tab, tab_id, f"\n[bold gray][Sistema - Resultado de Herramienta]:[/bold gray]\n{tool_result}")
                
                # Inyectar el resultado de vuelta en la conversación para que la IA sepa qué pasó
                history.append({"role": "user", "content": f"[Resultado de herramienta local]:\n{tool_result}"})
            else:
                break
                
        self.call_from_thread(self.enable_input)

    def parse_and_execute_xml_tool(self, text: str) -> tuple[bool, str]:
        """Analiza expresiones regulares en busca de etiquetas XML de herramientas locales."""
        # 1. <list_directory/>
        m = re.search(r'<list_directory\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, list_directory(path)

        # 2. <make_directory/>
        m = re.search(r'<make_directory\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, make_directory(path)

        # 3. <read_file/>
        m = re.search(r'<read_file\s+path=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, read_file(path)

        # 4. <write_file>content</write_file>
        m = re.search(r'<write_file\s+path=[\"\'](.*?)[\"\']\s*>(.*?)</write_file>', text, re.DOTALL)
        if m:
            path, content = m.group(1), m.group(2)
            return True, write_file(path, content)

        # 5. <patch_file>...<search>...<replace>...</patch_file>
        m = re.search(r'<patch_file\s+path=[\"\'](.*?)[\"\']\s*>(.*?)</patch_file>', text, re.DOTALL)
        if m:
            path, raw_patch = m.group(1), m.group(2)
            search_match = re.search(r'<search>(.*?)</search>', raw_patch, re.DOTALL)
            replace_match = re.search(r'<replace>(.*?)</replace>', raw_patch, re.DOTALL)
            if search_match and replace_match:
                return True, patch_file(path, search_match.group(1), replace_match.group(1))
            return True, "[Error] Formato de patch_file incorrecto. Debe contener <search> y <replace>."

        # 6. <move_file/>
        m = re.search(r'<move_file\s+source=[\"\'](.*?)[\"\']\s+destination=[\"\'](.*?)[\"\']\s*/>', text)
        if m:
            src, dest = m.group(1), m.group(2)
            return True, move_file(src, dest)

        return False, ""

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()
