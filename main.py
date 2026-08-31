# -*- coding: utf-8 -*-
import typer
from src.tui import AVFenixApp

app = typer.Typer(help="AVFenix Coder - Agente de Codificación con Interfaz de Terminal (TUI)")

@app.command()
def start():
    """
    Inicia la interfaz gráfica de terminal (TUI) de AVFenix Coder.
    """
    tui_app = AVFenixApp()
    tui_app.run()

if __name__ == "__main__":
    app()