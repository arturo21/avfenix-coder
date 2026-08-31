# AVFenix Coder

## Requisitos
* Python >= 3.10
* API Key de OpenRouter

## Instalación rápida
1. Crea y activa un entorno virtual de Python:
```bash
   python -m venv venv
   source venv/bin/activate  # En Windows: venv\Scripts\activate
```

Instala el proyecto en modo editable (esto registrará el comando avfenix):
```bash
pip install -e .
```
    Configura tu archivo .env en la raíz del proyecto:

OPENROUTER_API_KEY=XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx
OPENROUTER_MODEL=google/gemini-2.5-free

Ejecución
Ejecuta el agente utilizando el comando registrado o directamente con python:

```bash
python main.py --prompt "Saluda al equipo de AVFenix"
```

o:

```bash
avfenix --prompt "Genera una función de ordenamiento rápido en Python"
```
---
