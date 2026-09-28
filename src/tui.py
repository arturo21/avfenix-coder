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
        """
        Bucle de ejecución autónomo (Agent Loop) con soporte multi-herramientas XML.
        """
        if not self.client:
            self.call_from_thread(self.chat_log.write, "[red]Error: Conexión no inicializada.[/red]")
            self.call_from_thread(self.enable_input)
            return

        self.chat_history.append({"role": "user", "content": user_prompt})
        
        loop_active = True
        max_iterations = 5
        iteration = 0

        while loop_active and iteration < max_iterations:
            iteration += 1
            self.call_from_thread(self.chat_log.write, f"[italic yellow]AVFenix está procesando (Paso {iteration})...[/italic yellow]")
            
            messages = [{"role": "system", "content": SYSTEM_PROMPT}] + self.chat_history

            response_text = ""
            success = False
            for current_model in self.candidates:
                try:
                    response = self.client.chat.completions.create(
                        model=current_model,
                        messages=messages
                    )
                    response_text = response.choices[0].message.content
                    success = True
                    break
                except Exception as e:
                    self.call_from_thread(self.chat_log.write, f"[yellow]⚠️ Reintentando por error en {current_model}: {e}[/yellow]")
            
            if not success or not response_text:
                self.call_from_thread(self.chat_log.write, "[bold red]❌ Error: Todos los modelos de OpenRouter fallaron.[/bold red]")
                break

            self.chat_history.append({"role": "assistant", "content": response_text})

            clean_text = re.sub(r"<write_file.*?>.*?</write_file>", "", response_text, flags=re.DOTALL)
            clean_text = re.sub(r"<patch_file.*?>.*?</patch_file>", "", clean_text, flags=re.DOTALL)
            clean_text = re.sub(r"<read_file.*?/>", "", clean_text)
            clean_text = re.sub(r"<list_directory.*?/>", "", clean_text)
            clean_text = re.sub(r"<make_directory.*?/>", "", clean_text)
            clean_text = re.sub(r"<move_file.*?/>", "", clean_text)
            clean_text = clean_text.strip()

            if clean_text:
                self.call_from_thread(self.chat_log.write, f"\n[bold green]AVFenix Coder:[/bold green]\n{clean_text}")

            tool_calls = []
            
            for m in re.finditer(r'<list_directory\s+path=["\'](.*?)["\']\s*/>|<list_directory\s*/>', response_text):
                path = m.group(1) if m.group(1) else "."
                tool_calls.append(("list_directory", {"path": path}))

            for m in re.finditer(r'<read_file\s+path=["\'](.*?)["\']\s*/>', response_text):
                tool_calls.append(("read_file", {"path": m.group(1)}))

            for m in re.finditer(r'<make_directory\s+path=["\'](.*?)["\']\s*/>', response_text):
                tool_calls.append(("make_directory", {"path": m.group(1)}))

            for m in re.finditer(r'<move_file\s+source=["\'](.*?)["\']\s+destination=["\'](.*?)["\']\s*/>', response_text):
                tool_calls.append(("move_file", {"source": m.group(1), "destination": m.group(2)}))

            for m in re.finditer(r'<write_file\s+path=["\'](.*?)["\']\s*>(.*?)</write_file>', response_text, flags=re.DOTALL):
                tool_calls.append(("write_file", {"path": m.group(1), "content": m.group(2)}))

            for m in re.finditer(r'<patch_file\s+path=["\'](.*?)["\']\s*>(.*?)</patch_file>', response_text, flags=re.DOTALL):
                path = m.group(1)
                patch_content = m.group(2)
                search_m = re.search(r'<search>(.*?)</search>', patch_content, flags=re.DOTALL)
                replace_m = re.search(r'<replace>(.*?)</replace>', patch_content, flags=re.DOTALL)
                if search_m and replace_m:
                    tool_calls.append(("patch_file", {"path": path, "search": search_m.group(1), "replace": replace_m.group(1)}))

            if not tool_calls:
                loop_active = False
            else:
                system_feedback = []
                for tool_name, args in tool_calls:
                    self.call_from_thread(self.action_log.write, f"⚙️ [cyan]Ejecutando {tool_name}...[/cyan]")
                    
                    if tool_name == "list_directory":
                        res = list_directory(args["path"])
                    elif tool_name == "read_file":
                        res = read_file(args["path"])
                    elif tool_name == "make_directory":
                        res = make_directory(args["path"])
                    elif tool_name == "move_file":
                        res = move_file(args["source"], args["destination"])
                    elif tool_name == "write_file":
                        res = write_file(args["path"], args["content"])
                    elif tool_name == "patch_file":
                        res = patch_file(args["path"], args["search"], args["replace"])
                    else:
                        res = "Error: Herramienta desconocida."

                    self.call_from_thread(self.action_log.write, f"✅ [green]Hecho: {tool_name}[/green]")
                    system_feedback.append(f"[Resultado de herramienta '{tool_name}': {res}]")

                feedback_msg = "\n".join(system_feedback)
                self.chat_history.append({"role": "user", "content": feedback_msg})

        self.call_from_thread(self.enable_input)

    def enable_input(self) -> None:
        self.user_input.disabled = False
        self.user_input.focus()
