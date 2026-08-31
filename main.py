# -*- coding: utf-8 -*-
import typer
from src.tui import AVFenixApp

app = typer.Typer(
    help="AVFenix Coder - Agente de Codificación Autónomo con Interfaz de Terminal (TUI)",
    add_completion=False
)

@app.command()
def start():
    """
    Inicia la interfaz gráfica de terminal (TUI) interactiva de AVFenix Coder.
    """
    tui_app = AVFenixApp()
    tui_app.run()

@app.callback(invoke_without_command=True)
def default(ctx: typer.Context):
    """
    Punto de entrada por defecto. Si no se pasa ningún comando,
    inicia la TUI automáticamente para mayor comodidad.
    """
    if ctx.invoked_subcommand is None:
        start()

if __name__ == "__main__":
    app()