# -*- coding: utf-8 -*-
import openai
from textual.app import App, ComposeResult
from textual.containers import Vertical, Horizontal
from textual.widgets import Header, Footer, Input, RichLog, Button, Label
from textual import work
from src.config import OPENROUTER_API_KEY, get_available_free_models, select_best_free_model, FALLBACK_FREE_MODELS

class AVFenixApp(App):
    """
    Interfaz Gráfica de Terminal (TUI) para AVFenix Coder.
    Modelada para ser 100% compatible con múltiples versiones de Textual.
    """
    CSS = """
    Screen {
        background: #1e1e2e;
    }
    #sidebar {
        width: 30;
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
    """

    TITLE = "AVFenix Coder"
    SUBTITLE = "TUI Autónoma de Codificación 100% Gratuita"
    BINDINGS = [("q", "quit", "Salir")]

    def __init__(self):
        super().__init__()
        self.selected_model = "Buscando..."
        self.client = None
        self.candidates = FALLBACK_FREE_MODELS

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
                yield Label("\n[bold gray]Instrucciones:[/bold gray]\nEscribe en la caja de texto y presiona Enter o el botón Enviar.\n\nPresiona 'Q' para salir de la aplicación de forma segura.")
            with Vertical(id="chat-container"):
                self.chat_log = RichLog(id="chat-area", highlight=True, markup=True)
                yield self.chat_log
                with Horizontal(id="input-container"):
                    self.user_input = Input(placeholder="Escribe tu consulta o tarea aquí...")
                    yield self.user_input
                    yield Button("Enviar", variant="primary", id="send-btn")
        yield Footer()

    def on_mount(self) -> None:
        self.chat_log.write("[bold green]¡Bienvenido a la interfaz gráfica de AVFenix Coder![/bold green]\nBuscando conexión segura con OpenRouter...\n")
        self.initialize_agent()

    @work(thread=True)
    def initialize_agent(self) -> None:
        if not OPENROUTER_API_KEY:
            self.call_from_thread(self.update_status, "❌ Falta .env", "Configura .env", "status-loading")
            self.call_from_thread(self.chat_log.write, "[bold red]Error: No se encontró OPENROUTER_API_KEY en tu archivo .env[/bold red]")
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
            self.call_from_thread(self.chat_log.write, f"Modelo óptimo seleccionado: [bold cyan]{best_model}[/bold cyan]\n")
        except Exception as e:
            self.call_from_thread(self.chat_log.write, f"[yellow]Advertencia al cargar modelos: {e}. Usando modelos de respaldo seguro.[/yellow]")
            self.selected_model = FALLBACK_FREE_MODELS
            self.call_from_thread(self.update_status, "⚠️ Modo Seguro", self.selected_model, "status-loading")

    def update_status(self, status: str, model: str, css_class: str) -> None:
        self.status_label.update(status)
        self.status_label.set_classes(css_class)
        self.model_label.update(model)
        self.model_label.set_classes(css_class)

    # =========================================================================
    # 🔄 MANEJO NATIVO DE EVENTOS POR CONVENCIÓN DE NOMBRES (100% COMPATIBLE)
    # =========================================================================

    def on_input_submitted(self, event: Input.Submitted) -> None:
        """Se activa automáticamente cuando el usuario pulsa Enter en el campo de texto."""
        prompt = event.value.strip()
        if not prompt:
            return
        self.user_input.value = ""
        self.chat_log.write(f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
        self.user_input.disabled = True
        self.query_model(prompt)

    def on_button_pressed(self, event: Button.Pressed) -> None:
        """Se activa automáticamente cuando el usuario hace clic en cualquier botón."""
        if event.button.id == "send-btn":
            prompt = self.user_input.value.strip()
            if not prompt:
                return
            self.user_input.value = ""
            self.chat_log.write(f"\n[bold cyan]Tú:[/bold cyan] {prompt}")
            self.user_input.disabled = True
            self.query_model(prompt)

    # =========================================================================

    @work(thread=True)
    def query_model(self, prompt: str) -> None:
        if not self.client:
            self.call_from_thread(self.chat_log.write, "[red]Error: API Key no configurada.[/red]")
            self.call_from_thread(self.enable_input)
            return

        self.call_from_thread(self.chat_log.write, "[italic yellow]AVFenix Coder está analizando tu solicitud...[/italic yellow]")
        
        success = False
        for current_model in self.candidates:
            try:
                response = self.client.chat.completions.create(
                    model=current_model,
                    messages=[
                        {"role": "system", "content": "Eres AVFenix Coder, un asistente de codificación experto e inteligente."},
                        {"role": "user", "content": prompt}
                    ]
                )
                
                answer = response.choices[0].message.content
                
                self.call_from_thread(self.chat_log.write, f"\n[bold green]AVFenix Coder ({current_model}):[/bold green]")
                self.call_from_thread(self.chat_log.write, answer)
                
                if self.selected_model != current_model:
                    self.selected_model = current_model
                    self.call_from_thread(self.update_status, "✅ Conectado", current_model, "status-ok")
                
                success = True
                break
            except Exception as e:
                self.call_from_thread(self.chat_log.write, f"[yellow]⚠️ El modelo '{current_model}' falló: {e}. Intentando con el siguiente...[/yellow]")
        
        if not success:
            self.call_from_thread(self.chat_log.write, "\n[bold red]❌ Error crítico: Todos los modelos de respaldo fallaron.[/bold red]")

        self.call_from_thread(self.enable_input)

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()