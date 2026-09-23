# -*- coding: utf-8 -*-
"""
main.py - Punto de entrada principal para AVFenix Coder.
Garantiza la correcta resolución de rutas de importación y lanza la TUI de Textual.
"""
import os
import sys

# Asegurar que el directorio raíz del proyecto esté en el PATH de Python
PROJECT_ROOT = os.path.dirname(os.path.abspath(__file__))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

try:
    from src.tui import AVFenixApp
except ImportError as e:
    print(f"❌ Error de importación al cargar AVFenix Coder: {e}")
    print(f"Asegúrate de ejecutar el comando desde la raíz del proyecto o tener la carpeta 'src/' presente.")
    sys.exit(1)


def main():
    """Lanza la interfaz gráfica de terminal (TUI) de AVFenix Coder."""
    try:
        app = AVFenixApp()
        app.run()
    except Exception as e:
        print(f"❌ Error al iniciar la interfaz gráfica: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()