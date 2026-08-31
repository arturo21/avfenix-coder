# -*- coding: utf-8 -*-
import openai
import re
from textual.app import App, ComposeResult
from textual.containers import Vertical, Horizontal
from textual.widgets import Header, Footer, Input, RichLog, Button, Label
from textual import work
from src.config import OPENROUTER_API_KEY, get_available_free_models, select_best_free_model, FALLBACK_FREE_MODELS
from src.prompts import SYSTEM_PROMPT
from src.tools import read_file, write_file, patch_file, make_directory, list_directory, move_file

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
    #chat-area {
        height: 1fr;
        border: solid #45475a;
        background: #181825;
        margin-bottom: 1;
    }
    #input-container {
        height: auto;
        layout: horizontal;
    }
    Input {
        width: 1fr;
        border: tall #89b4fa;
        background: #313244;
        color: #cdd6f4;
    }
    Button {
        margin-left: 1;
        background: #89b4fa;
        color: #11111b;
        border: none;
    }
    Button:hover {
        background: #b4befe;
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
    """

    TITLE = "AVFenix Coder"
    SUBTITLE = "Agente Autónomo de Codificación"
    BINDINGS = [("q", "quit", "Salir")]

    def __init__(self):
        super().__init__()
        self.selected_model = "Buscando..."
        self.client = None
        self.candidates = FALLBACK_FREE_MODELS
        self.chat_history = []  # Memoria conversacional

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
                yield Label("\n[bold]Historial de Acciones:[/bold]")
                self.action_log = RichLog(classes="history-area", highlight=True, markup=True)
                yield self.action_log
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nEscribe en el chat para interactuar con tu agente autónomo de desarrollo.")
            with Vertical(id="chat-container"):
                self.chat_log = RichLog(id="chat-area", highlight=True, markup=True)
                yield self.chat_log
                with Horizontal(id="input-container"):
                    self.user_input = Input(placeholder="Pídele crear, editar o analizar archivos...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    def on_mount(self) -> None:
        self.chat_log.write("[bold green]¡Bienvenido a la interfaz autónoma de AVFenix Coder![/bold green]\nInicializando entorno asíncrono...\n")
        self.initialize_agent()

    @work(thread=True)
    def initialize_agent(self) -> None:
        if not OPENROUTER_API_KEY:
            self.call_from_thread(self.update_status, "❌ Falta .env", "Configura .env", "status-loading")
            self.call_from_thread(self.chat_log.write, "[bold red]Error: No se encontró la clave en tu .env[/bold red]")
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
            self.call_from_thread(self.chat_log.write, f"[green]Conectado con éxito a OpenRouter.[/green]")
            self.call_from_thread(self.chat_log.write, f"Modelo autónomo activo: [bold cyan]{best_model}[/bold cyan]\n")
        except Exception as e:
            # ✅ CORRECCIÓN CRUCIAL: Usamos FALLBACK_FREE_MODELS[0] (un string) en lugar de la lista completa
            self.selected_model = FALLBACK_FREE_MODELS[0]
            self.call_from_thread(self.update_status, "⚠️ Modo Respaldo", self.selected_model, "status-loading")

    def update_status(self, status: str, model: str, css_class: str) -> None:
        self.status_label.update(status)
        self.status_label.set_classes(css_class)
        self.model_label.update(model)
        self.model_label.set_classes(css_class)

    def on_input_submitted(self, event: Input.Submitted) -> None:
        prompt = event.value.strip()
        if not prompt:
            return
        self.user_input.value = ""
        self.chat_log.write(f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
        self.user_input.disabled = True
        self.run_agent_loop(prompt)

    def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "send-btn":
            prompt = self.user_input.value.strip()
            if not prompt:
                return
            self.user_input.value = ""
            self.chat_log.write(f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
            self.user_input.disabled = True
            self.run_agent_loop(prompt)

    @work(thread=True)
    def run_agent_loop(self, user_prompt: str) -> None:
        """Bucle de ejecución autónomo (Agent Loop) que intercepta XML y ejecuta herramientas localmente."""
        if not self.client:
            self.call_from_thread(self.chat_log.write, "[red]Error: API Key ausente.[/red]")
            self.call_from_thread(self.enable_input)
            return

        # Añadir prompt del usuario al historial
        self.chat_history.append({"role": "user", "content": user_prompt})
        
        # Límite de seguridad de iteraciones autónomas para evitar bucles infinitos
        max_iterations = 8
        current_iteration = 0
        
        while current_iteration < max_iterations:
            current_iteration += 1
            self.call_from_thread(self.chat_log.write, f"[italic yellow]🦅 Analizando archivos y procesando paso {current_iteration}...[/italic yellow]")
            
            success = False
            response_text = ""
            active_model = ""
            
            # Intentar llamada a OpenRouter con reintentos automáticos de resiliencia
            for current_model in self.candidates:
                try:
                    response = self.client.chat.completions.create(
                        model=current_model,
                        messages=[{"role": "system", "content": SYSTEM_PROMPT}] + self.chat_history
                    )
                    # Indexado de lista de opciones corregido para evitar AttributeErrors
                    response_text = response.choices[0].message.content
                    active_model = current_model
                    success = True
                    break
                except Exception as e:
                    self.call_from_thread(self.chat_log.write, f"[yellow]⚠️ Fallo con {current_model}: {e}. Intentando respaldo...[/yellow]")
            
            if not success:
                self.call_from_thread(self.chat_log.write, "[bold red]❌ Error crítico: Ningún modelo gratuito pudo procesar tu solicitud.[/bold red]")
                break

            # Mostrar respuesta intermedia del agente
            self.call_from_thread(self.chat_log.write, f"\n[bold green]AVFenix Coder ({active_model}):[/bold green]")
            self.call_from_thread(self.chat_log.write, response_text)
            
            # Guardar en memoria la respuesta de la IA
            self.chat_history.append({"role": "assistant", "content": response_text})

            # Analizar si la IA quiere ejecutar alguna herramienta XML
            tool_executed, tool_result = self.parse_and_execute_xml_tool(response_text)
            
            if tool_executed:
                # Escribir el resultado de la acción local en la caja de sistema
                self.call_from_thread(self.action_log.write, f"⚙️ {tool_result.split(':')}")
                self.call_from_thread(self.chat_log.write, f"\n[bold gray][Sistema - Resultado de Herramienta]:[/bold gray]\n{tool_result}")
                
                # Inyectar el resultado de vuelta en la conversación para que la IA sepa qué pasó
                self.chat_history.append({"role": "user", "content": f"[Resultado de herramienta local]:\n{tool_result}"})
            else:
                # Si el modelo no invocó ninguna etiqueta XML, ha concluido la tarea autónoma
                break
                
        self.call_from_thread(self.enable_input)

    def parse_and_execute_xml_tool(self, text: str) -> tuple[bool, str]:
        """Analiza expresiones regulares en busca de etiquetas XML de herramientas locales."""
        # 1. <list_directory/>
        m = re.search(r'<list_directory\s+path=["\'](.*?)["\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, list_directory(path)

        # 2. <make_directory/>
        m = re.search(r'<make_directory\s+path=["\'](.*?)["\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, make_directory(path)

        # 3. <read_file/>
        m = re.search(r'<read_file\s+path=["\'](.*?)["\']\s*/>', text)
        if m:
            path = m.group(1)
            return True, read_file(path)

        # 4. <write_file>content</write_file>
        m = re.search(r'<write_file\s+path=["\'](.*?)["\']\s*>(.*?)</write_file>', text, re.DOTALL)
        if m:
            path, content = m.group(1), m.group(2)
            return True, write_file(path, content)

        # 5. <patch_file>...<search>...<replace>...</patch_file>
        m = re.search(r'<patch_file\s+path=["\'](.*?)["\']\s*>(.*?)</patch_file>', text, re.DOTALL)
        if m:
            path, raw_patch = m.group(1), m.group(2)
            search_match = re.search(r'<search>(.*?)</search>', raw_patch, re.DOTALL)
            replace_match = re.search(r'<replace>(.*?)</replace>', raw_patch, re.DOTALL)
            if search_match and replace_match:
                return True, patch_file(path, search_match.group(1), replace_match.group(1))
            return True, "[Error] Formato de patch_file incorrecto. Debe contener <search> y <replace>."

        # 6. <move_file/>
        m = re.search(r'<move_file\s+source=["\'](.*?)["\']\s+destination=["\'](.*?)["\']\s*/>', text)
        if m:
            src, dest = m.group(1), m.group(2)
            return True, move_file(src, dest)

        return False, ""

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()
