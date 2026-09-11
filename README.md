# 🦅 AVFenix Coder

**Agente Autónomo de Codificación en Terminal (TUI) con Arquitectura Multi-Pestaña, Suite de 16 HerramientasLocales, Integración MCP y Persistencia de Sesión.**

[![Python Version](https://img.shields.io/badge/python-3.10%2B-blue.svg)](https://www.python.org/)
[![UI Framework](https://img.shields.io/badge/UI-Textual-green.svg)](https://textual.textualize.io/)
[![Protocol](https://img.shields.io/badge/Protocol-MCP%20v1.0-orange.svg)](https://modelcontextprotocol.io/)
[![License](https://img.shields.io/badge/license-MIT-purple.svg)](LICENSE)

---

## 🌟 Descripción General

**AVFenix Coder** es un agente de software autónomo de desarrollo diseñado para operar directamente desde la terminal. Ofrece una interfaz gráfica moderna (TUI) construida sobre **Textual**, capaz de orquestar modelos de lenguaje a través de **OpenRouter** para inspeccionar, crear, editar (vía parches quirúrgicos), probar y ejecutar código de forma totalmente autónoma.

Además, incorpora soporte nativo para **Model Context Protocol (MCP)**, actuando como *MCP Host* y conectándose con servidores externos (como **Unity Editor**, GitHub, bases de datos o servicios en la nube).

---

## 🚀 Características Clave

### 💻 1. Interfaz Gráfica Terminal (TUI) Avanzada
- **Navegación Multi-Pestaña**: Abre múltiples conversaciones aisladas (`🆕 Nueva Conversación`), cada una con su propio historial de chat e hilo de memoria independiente.
- **Entrada Multilínea Avanzada (`ChatInput`)**:
  - `Shift + Enter`: Inserta saltos de línea para redactar prompts complejos o bloques de código.
  - `Enter`: Envía el mensaje al agente de forma instantánea.
- **Historial de Prompts Navegable**: Utiliza las teclas **`Arriba`** y **`Abajo`** para navegar por tus comandos anteriores, con guardado automático de borradores.
- **Copiado al Portapapeles (`📋 Copiar`)**: Botón dedicado por pestaña que limpia códigos de control e inyecta la conversación formateada en el portapapeles del sistema operativo.
- **Tema Visual Catppuccin**: Paleta de colores oscuros con indicadores de estado en tiempo real.

---

### 🧰 2. Suite Extendida de 16 Herramientas Locales

AVFenix Coder cuenta con un motor de ejecución XML que intercepta y procesa 16 acciones directamente en tu disco local:

| Herramienta | Etiqueta XML / Comando | Descripción |
|---|---|---|
| **`list_directory`** | `<list_directory path="..."/>` | Muestra archivos y carpetas en una ruta. |
| **`make_directory`** | `<make_directory path="..."/>` | Crea carpetas y subcarpetas jerárquicas. |
| **`read_file`** | `<read_file path="..."/>` | Lee el contenido completo de un archivo de texto. |
| **`write_file`** | `<write_file path="...">contenido</write_file>` | Crea o sobrescribe un archivo completo. |
| **`patch_file`** | `<patch_file path="..."><search>...</search><replace>...</replace></patch_file>` | Modificación quirúrgica reemplazando bloques específicos de código. |
| **`move_file`** | `<move_file source="..." destination="..."/>` | Mueve o renombra archivos y directorios. |
| **`delete_file`** | `<delete_file path="..."/>` | Elimina archivos o carpetas completas. |
| **`tree_directory`** | `<tree_directory path="..." max_depth="3"/>` | Genera una vista en árbol de la estructura de directorios. |
| **`get_file_info`** | `<get_file_info path="..."/>` | Muestra tamaño, número de líneas y fecha de modificación. |
| **`search_code`** | `<search_code query="..." directory="..."/>` | Búsqueda tipo *grep* en todos los archivos del proyecto. |
| **`find_files`** | `<find_files pattern="*.py" directory="..."/>` | Encuentra archivos por patrón comodín (*wildcard*). |
| **`execute_command`** | `<execute_command timeout="30">comando</execute_command>` | Ejecuta comandos de terminal de forma segura. |
| **`run_tests`** | `<run_tests test_path="tests"/>` | Ejecuta suites de pruebas unitarias (`pytest` / `unittest`). |
| **`git_status`** | `<git_status directory="..."/>` | Consulta el estado y cambios del repositorio Git. |
| **`create_backup`** | `<create_backup path="..."/>` | Genera una copia con marca de tiempo en `.avfenix_backups/`. |
| **`fetch_web_page`** | `<fetch_web_page url="..."/>` | Extrae y limpia el texto de una página web o documentación. |

---

### 🔌 3. Integración MCP (Model Context Protocol) & Unity Editor

- **Arquitectura Host-Cliente MCP**: Implementa `mcp_client.py` con soporte para transportes `stdio` y `sse`, reconexión con *backoff exponencial* y consumo de las primitivas **Tools**, **Resources** y **Prompts**.
- **Servidor MCP para Unity**: Incluye `unity_mcp_server.py` y el puente en C# `UnityMCPBridge.cs` para permitir que el agente interactúe en tiempo real con el **Unity Editor**:
  - Crear objetos y prefabs (`unity_create_game_object`).
  - Ajustar posiciones, rotaciones y escalas (`unity_set_transform`).
  - Asignar componentes y físicas (`unity_add_component`).
  - Generar y compilar scripts de C# (`unity_create_script`).
  - Inspeccionar la consola de errores de Unity (`unity_get_console_logs`).
  - Controlar la reproducción del juego (`unity_toggle_play_mode`).

---

### 💾 4. Persistencia & Memoria de Sesión (Fase 6)

- **Auto-Guardado en Tiempo Real**: Guarda atómicamente el estado del entorno en `avfenix_session.json` tras cada interacción, creación o cierre de pestaña.
- **Restauración Completa**: Al iniciar, restaura todas las pestañas activas, sus historiales de chat, el foco de navegación y el historial de comandos de la caja de texto.
- **Copia Atómica Anti-Corrupción**: Escribe primero en `.tmp` antes de reemplazar el archivo principal.

---

### 🛡️ 5. Blindaje Financiero

AVFenix Coder consulta automáticamente la API de **OpenRouter** para seleccionar modelos de lenguaje potentes que sean **100% gratuitos** (como `Llama 3.1 8B`, `Qwen 2.5`, `Mistral` o `Phi-3`), filtrando modelos de pago para evitar cualquier consumo de saldo.

---

## 📂 Estructura del Proyecto

```text
avfenix-coder/
├── main.py                    # Punto de entrada principal de la TUI
├── config.py                  # Configuración de OpenRouter y selección de modelos
├── prompts.py                 # System Prompts y reglas de comportamiento del agente
├── tools.py                   # Suite de 16 herramientas locales en Python
├── tui.py                     # Interfaz de usuario con Textual, pestañas y memoria
├── session_manager.py         # Gestor de persistencia en avfenix_session.json
├── mcp_client.py              # Cliente MCP asíncrono (stdio/sse)
├── main_mcp.py                # Script de prueba y demostración del cliente MCP
├── unity_mcp_server.py        # Servidor FastMCP en Python para Unity
├── UnityMCPBridge.cs          # Listener en C# para Assets/Editor/ de Unity
├── pyproject.toml             # Configuración del paquete y dependencias
├── app_screenshot.png         # Captura de pantalla de la TUI
└── README.md                  # Documentación oficial del repositorio
```

---

## 🛠️ Instalación y Requisitos

### Requisitos Previos
- **Python 3.10** o superior.
- Una clave de API gratuita de [OpenRouter](https://openrouter.ai/).

### 1. Clonar el repositorio e instalar dependencias
```bash
git clone https://github.com/tu-usuario/avfenix-coder.git
cd avfenix-coder

# Crear e activar entorno virtual
python3 -m venv .venv
source .venv/bin/activate  # En Windows: .venv\Scripts\activate

# Instalar dependencias
pip install textual openai mcp
```

### 2. Configurar la clave de API
Crea un archivo `.env` en la raíz del proyecto:
```env
OPENROUTER_API_KEY=tu_api_key_aqui
```

---

## 💻 Guía de Uso

Para iniciar **AVFenix Coder**, ejecuta:
```bash
python main.py
```

### Atajos de Teclado
- `Shift + Enter`: Salto de línea en el campo de chat.
- `Enter`: Enviar mensaje.
- `Flecha Arriba / Abajo`: Navegar por el historial de prompts enviados.
- `N`: Crear una nueva pestaña de conversación.
- `Q`: Salir de la aplicación (se guardará la sesión automáticamente).

---

## 🖼️ Vista Previa de la Interfaz

![AVFenix Coder Screenshot](app_screenshot.png)

---

## 📄 Licencia

Este proyecto está bajo la Licencia **MIT**. Consulta el archivo `LICENSE` para más detalles.