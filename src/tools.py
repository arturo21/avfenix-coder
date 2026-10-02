# -*- coding: utf-8 -*-
"""
tools.py - Suite completa de Herramientas Nativas de Disco, Sistema, Fuentes, Plugins,
MCP Registry, AST, Swarm, Git Avanzado, Sandbox y Enriquecimiento Visual TUI (Paso 6).
"""

import os
import sys
import shutil
import subprocess
import fnmatch
import time
from datetime import datetime
import urllib.request
import re
import logging

from src.sources_manager import SourcesManager
from src.plugins.plugin_manager import PluginManager
from src.plugins.package_manager import PackageManager
from src.user_style_memory import UserStyleMemory
from src.mcp_registry import MCPRegistry
from src.code_ast_indexer import ASTCodeIndexer
from src.multi_agent_swarm import SwarmOrchestrator
from src.git_advanced_manager import GitAdvancedManager
from src.sandbox_executor import SandboxExecutor
from src.tui_enrichment_manager import TUIEnrichmentManager

logger = logging.getLogger("AVFenixTools")

CURRENT_WORKING_DIR = os.getcwd()


def set_working_dir(path: str) -> str:
    """Cambia el directorio raíz de trabajo activo para todas las operaciones del agente."""
    global CURRENT_WORKING_DIR
    try:
        abs_path = os.path.abspath(path)
        if not os.path.exists(abs_path):
            return f"Error: El directorio '{abs_path}' no existe."
        if not os.path.isdir(abs_path):
            return f"Error: '{abs_path}' no es un directorio válido."

        os.chdir(abs_path)
        CURRENT_WORKING_DIR = abs_path
        return f"Éxito: Directorio de trabajo establecido en '{CURRENT_WORKING_DIR}'."
    except Exception as e:
        return f"Error al cambiar directorio de trabajo: {e}"


def add_context(path_or_text: str) -> str:
    """Lee un archivo de documentación o regla de negocio para inyectarlo como contexto."""
    try:
        if os.path.exists(path_or_text) and os.path.isfile(path_or_text):
            with open(path_or_text, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
            return f"[Contexto Inyectado desde '{path_or_text}']:\n{content}"
        else:
            return f"[Contexto Inyectado]:\n{path_or_text}"
    except Exception as e:
        return f"Error al cargar contexto: {e}"


def read_file(path: str) -> str:
    """Lee el contenido de un archivo de texto."""
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            return f.read()
    except Exception as e:
        return f"Error al leer el archivo: {e}"


def write_file(path: str, content: str) -> str:
    """Crea o sobrescribe un archivo completo, creando carpetas intermedias si no existen."""
    try:
        dir_name = os.path.dirname(path)
        if dir_name and not os.path.exists(dir_name):
            os.makedirs(dir_name, exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        return f"Éxito: Archivo '{path}' creado/escrito correctamente ({len(content)} caracteres)."
    except Exception as e:
        return f"Error al escribir el archivo: {e}"


def patch_file(path: str, search: str, replace: str) -> str:
    """Realiza una modificación quirúrgica en un archivo existente reemplazando un bloque específico."""
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        if search not in content:
            return f"Error: No se encontró el bloque a buscar en '{path}'."
        updated_content = content.replace(search, replace, 1)
        with open(path, "w", encoding="utf-8") as f:
            f.write(updated_content)
        return f"Éxito: Archivo '{path}' modificado quirúrgicamente."
    except Exception as e:
        return f"Error al modificar el archivo: {e}"


def make_directory(path: str) -> str:
    """Crea un directorio y sus carpetas padres si no existen."""
    try:
        os.makedirs(path, exist_ok=True)
        return f"Éxito: Directorio '{path}' creado correctamente."
    except Exception as e:
        return f"Error al crear el directorio: {e}"


def list_directory(path: str = ".") -> str:
    """Lista el contenido de un directorio indicando si son archivos o carpetas."""
    try:
        if not os.path.exists(path):
            return f"Error: El directorio '{path}' no existe."
        items = os.listdir(path)
        if not items:
            return f"El directorio '{path}' está vacío."
        result = []
        for item in sorted(items):
            full_path = os.path.join(path, item)
            if os.path.isdir(full_path):
                result.append(f"[DIR] {item}/")
            else:
                result.append(f"[FILE] {item}")
        return "\n".join(result)
    except Exception as e:
        return f"Error al listar el directorio: {e}"


def move_file(source: str, destination: str) -> str:
    """Mueve o renombra un archivo o carpeta."""
    try:
        if not os.path.exists(source):
            return f"Error: El origen '{source}' no existe."
        dest_dir = os.path.dirname(destination)
        if dest_dir and not os.path.exists(dest_dir):
            os.makedirs(dest_dir, exist_ok=True)
        shutil.move(source, destination)
        return f"Éxito: '{source}' movido/renombrado a '{destination}'."
    except Exception as e:
        return f"Error al mover/renombrar: {e}"


def execute_command(command: str, timeout: int = 30) -> str:
    """Ejecuta un comando de consola del sistema operativo de forma segura."""
    try:
        result = subprocess.run(
            command,
            shell=True,
            text=True,
            capture_output=True,
            timeout=timeout
        )
        stdout = result.stdout.strip()
        stderr = result.stderr.strip()
        exit_code = result.returncode

        output = [f"[Exit Code: {exit_code}]"]
        if stdout:
            output.append(f"--- STDOUT ---\n{stdout}")
        if stderr:
            output.append(f"--- STDERR ---\n{stderr}")
        if not stdout and not stderr:
            output.append("(El comando se ejecutó sin salida de texto)")

        return "\n".join(output)
    except subprocess.TimeoutExpired:
        return f"Error: El comando superó el tiempo límite de {timeout} segundos."
    except Exception as e:
        return f"Error al ejecutar comando: {e}"


def search_code(query: str, directory: str = ".") -> str:
    """Busca una cadena de texto o término de código en todos los archivos de un directorio."""
    try:
        if not os.path.exists(directory):
            return f"Error: El directorio '{directory}' no existe."

        matches = []
        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_mcp", ".avfenix_ast", ".avfenix_swarm", ".avfenix_sandbox"}

        for root, dirs, files in os.walk(directory):
            dirs[:] = [d for d in dirs if d not in ignore_dirs]
            for file in files:
                filepath = os.path.join(root, file)
                try:
                    with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
                        for line_num, line in enumerate(f, 1):
                            if query.lower() in line.lower():
                                matches.append(f"{filepath}:{line_num}: {line.strip()}")
                except Exception:
                    continue

        if not matches:
            return f"No se encontraron coincidencias para '{query}' en '{directory}'."

        if len(matches) > 100:
            return f"Se encontraron {len(matches)} coincidencias. Mostrando las primeras 100:\n" + "\n".join(matches[:100])
        return "\n".join(matches)
    except Exception as e:
        return f"Error al buscar código: {e}"


def find_files(pattern: str, directory: str = ".") -> str:
    """Busca archivos por patrón comodín (ej. '*.py', 'test_*.json') en un directorio."""
    try:
        if not os.path.exists(directory):
            return f"Error: El directorio '{directory}' no existe."

        matches = []
        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_mcp", ".avfenix_ast", ".avfenix_swarm", ".avfenix_sandbox"}

        for root, dirs, files in os.walk(directory):
            dirs[:] = [d for d in dirs if d not in ignore_dirs]
            for filename in fnmatch.filter(files, pattern):
                matches.append(os.path.join(root, filename))

        if not matches:
            return f"No se encontraron archivos que coincidan con '{pattern}' en '{directory}'."
        return "\n".join(sorted(matches))
    except Exception as e:
        return f"Error al buscar archivos: {e}"


def delete_file(path: str) -> str:
    """Elimina un archivo o directorio de forma definitiva."""
    try:
        if not os.path.exists(path):
            return f"Error: El elemento '{path}' no existe."
        if os.path.isdir(path):
            shutil.rmtree(path)
            return f"Éxito: Directorio '{path}' y todo su contenido fueron eliminados."
        else:
            os.remove(path)
            return f"Éxito: Archivo '{path}' eliminado correctamente."
    except Exception as e:
        return f"Error al eliminar '{path}': {e}"


def run_tests(test_path: str = ".") -> str:
    """Ejecuta pruebas unitarias usando pytest o unittest con prevención de pycache stale."""
    try:
        env = os.environ.copy()
        env["PYTHONPATH"] = os.getcwd() + ":" + env.get("PYTHONPATH", "")
        env["PYTHONDONTWRITEBYTECODE"] = "1"

        result = subprocess.run(
            [sys.executable, "-B", "-m", "pytest", test_path, "-v"],
            text=True,
            capture_output=True,
            timeout=60,
            env=env
        )
        if result.returncode in (0, 1) and "pytest" in result.stdout:
            return f"--- Resultados de Pytest ---\n{result.stdout}\n{result.stderr}".strip()

        result_unittest = subprocess.run(
            [sys.executable, "-B", "-m", "unittest", "discover", "-s", test_path],
            text=True,
            capture_output=True,
            timeout=60,
            env=env
        )
        return f"--- Resultados de Unittest ---\n{result_unittest.stdout}\n{result_unittest.stderr}".strip()
    except Exception as e:
        return f"Error al ejecutar pruebas: {e}"


def git_status(directory: str = ".") -> str:
    """Consulta el estado del repositorio Git en la carpeta indicada."""
    git_mgr = GitAdvancedManager(directory)
    if not git_mgr.is_git_repo():
        return "Error: No es un repositorio Git válido o fallo en comando."
    _, out, _ = git_mgr._run_git_cmd(["status", "-s"])
    if not out:
        return "Git Status: El árbol de trabajo está limpio (sin cambios pendientes)."
    return f"--- Git Status ---\n{out}"


def create_backup(path: str) -> str:
    """Crea una copia de seguridad con marca de tiempo en la carpeta .avfenix_backups/."""
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."

        backup_dir = ".avfenix_backups"
        os.makedirs(backup_dir, exist_ok=True)

        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = os.path.basename(path)
        backup_filename = f"{filename}_{timestamp}.bak"
        backup_path = os.path.join(backup_dir, backup_filename)

        shutil.copy2(path, backup_path)
        return f"Éxito: Copia de seguridad creada correctamente en '{backup_path}'."
    except Exception as e:
        return f"Error al crear backup de '{path}': {e}"


def get_file_info(path: str) -> str:
    """Obtiene metadatos detallados de un archivo o carpeta."""
    try:
        if not os.path.exists(path):
            return f"Error: El archivo o directorio '{path}' no existe."

        stats = os.stat(path)
        size_bytes = stats.st_size
        mod_time = datetime.fromtimestamp(stats.st_mtime).strftime("%Y-%m-%d %H:%M:%S")

        if os.path.isdir(path):
            file_count = sum(len(files) for _, _, files in os.walk(path))
            return f"[Directorio] {path}\nTamaño: {size_bytes} bytes\nÚltima modificación: {mod_time}\nTotal de archivos contenidos: {file_count}"

        line_count = 0
        try:
            with open(path, "r", encoding="utf-8", errors="ignore") as f:
                line_count = sum(1 for _ in f)
        except Exception:
            line_count = "N/A (archivo binario)"

        return (
            f"[Archivo] {path}\n"
            f"Tamaño: {size_bytes} bytes ({size_bytes / 1024:.2f} KB)\n"
            f"Líneas de texto: {line_count}\n"
            f"Última modificación: {mod_time}"
        )
    except Exception as e:
        return f"Error al obtener información de '{path}': {e}"


def fetch_web_page(url: str) -> str:
    """Descarga el contenido de texto visible de una página web o documentación."""
    try:
        req = urllib.request.Request(
            url,
            headers={"User-Agent": "AVFenixCoder/1.0 (Python/Textual Agent)"}
        )
        with urllib.request.urlopen(req, timeout=15) as response:
            html = response.read().decode("utf-8", errors="ignore")

        text = re.sub(r"<script.*?>.*?</script>", "", html, flags=re.DOTALL | re.IGNORECASE)
        text = re.sub(r"<style.*?>.*?</style>", "", text, flags=re.DOTALL | re.IGNORECASE)
        text = re.sub(r"<.*?>", " ", text)
        lines = (line.strip() for line in text.splitlines())
        chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
        clean_text = "\n".join(chunk for chunk in chunks if chunk)

        if len(clean_text) > 4000:
            return clean_text[:4000] + "\n\n...[Contenido truncado por longitud]..."
        return clean_text if clean_text else "No se pudo extraer texto visible de la página."
    except Exception as e:
        return f"Error al descargar la página web '{url}': {e}"


def tree_directory(path: str = ".", max_depth: int = 3) -> str:
    """Genera una vista jerárquica en árbol de la estructura de directorios."""
    try:
        if not os.path.exists(path):
            return f"Error: El directorio '{path}' no existe."

        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_mcp", ".avfenix_ast", ".avfenix_swarm", ".avfenix_sandbox"}
        tree_lines = [f"📦 {os.path.basename(os.path.abspath(path)) or path}"]

        def build_tree(current_dir, prefix="", current_depth=1):
            if current_depth > max_depth:
                return
            try:
                entries = sorted(os.listdir(current_dir))
            except PermissionError:
                return

            filtered_entries = [e for e in entries if e not in ignore_dirs]
            count = len(filtered_entries)

            for i, entry in enumerate(filtered_entries):
                is_last = (i == count - 1)
                connector = "└── " if is_last else "├── "
                full_path = os.path.join(current_dir, entry)

                if os.path.isdir(full_path):
                    tree_lines.append(f"{prefix}{connector}📁 {entry}/")
                    new_prefix = prefix + ("    " if is_last else "│   ")
                    build_tree(full_path, new_prefix, current_depth + 1)
                else:
                    tree_lines.append(f"{prefix}{connector}📄 {entry}")

        build_tree(path, "", 1)
        return "\n".join(tree_lines)
    except Exception as e:
        return f"Error al generar árbol de directorio: {e}"


# -------------------------------------------------------------------------
# 🎨 HERRAMIENTAS DE ENRIQUECIMIENTO VISUAL TUI (PASO 6)
# -------------------------------------------------------------------------

def ui_render_directory_tree(path: str = ".") -> str:
    """Genera una vista interactiva y estructurada en árbol del directorio indicado."""
    mgr = TUIEnrichmentManager()
    return mgr.render_directory_tree(path)


def ui_preview_file(filepath: str, max_lines: int = 150) -> str:
    """Genera una vista previa formateada con metadatos y código del archivo especificado."""
    mgr = TUIEnrichmentManager()
    return mgr.preview_file(filepath, max_lines)


def ui_render_markdown(path_or_content: str) -> str:
    """Renderiza de forma enriquecida un documento Markdown o spec.md."""
    mgr = TUIEnrichmentManager()
    return mgr.render_markdown_formatted(path_or_content)


def ui_render_status_table(category: str = "all") -> str:
    """Genera una vista de tabla con métricas y estado del sistema (MCP, AST, Swarm, Memoria, Sandbox)."""
    mgr = TUIEnrichmentManager()
    return mgr.render_status_table(category)


# -------------------------------------------------------------------------
# 🛡️ HERRAMIENTAS DE AISLAMIENTO Y SEGURIDAD (SANDBOX - PASO 5)
# -------------------------------------------------------------------------

def sandbox_execute_command(command: str = "", timeout: int = 30) -> str:
    """Ejecuta un comando de consola dentro del entorno sandbox seguro e insulado."""
    executor = SandboxExecutor()
    return executor.execute_command(command, timeout)


def sandbox_get_policy() -> str:
    """Obtiene la política de seguridad activa del entorno sandbox."""
    executor = SandboxExecutor()
    return executor.get_security_policy()


def sandbox_get_audit_log(limit: int = 10) -> str:
    """Obtiene el historial de auditoría de comandos ejecutados en el sandbox."""
    executor = SandboxExecutor()
    return executor.get_audit_log(limit)


# -------------------------------------------------------------------------
# 🔀 HERRAMIENTAS DE FLUJOS GIT AVANZADOS Y VISUALIZACIÓN DE DIFFS (PASO 4)
# -------------------------------------------------------------------------

def git_get_diff(filepath: str = "", cached: bool = False) -> str:
    """Obtiene las diferencias de código (git diff) unificadas para un archivo o todo el proyecto."""
    git_mgr = GitAdvancedManager()
    return git_mgr.get_diff(filepath, cached)


def git_create_branch(branch_name: str) -> str:
    """Crea y activa una nueva rama de funcionalidad (feature branch)."""
    git_mgr = GitAdvancedManager()
    return git_mgr.create_feature_branch(branch_name)


def git_smart_commit(message: str = "", auto_message: bool = True) -> str:
    """Agrega cambios al staging y realiza un commit con mensaje Conventional Commits auto-generado."""
    git_mgr = GitAdvancedManager()
    return git_mgr.commit_changes(message, auto_message)


def git_generate_pr_summary(base_branch: str = "main") -> str:
    """Genera un informe completo para Pull Request (PR) comparando ramas en Git."""
    git_mgr = GitAdvancedManager()
    return git_mgr.create_pull_request_summary(base_branch)


# -------------------------------------------------------------------------
# 🤖 HERRAMIENTAS DE ENJAMBRE Y ORQUESTACIÓN MULTI-AGENTE (PASO 3)
# -------------------------------------------------------------------------

def spawn_subagent(role: str, task: str, context: str = "") -> str:
    """Instancia y delega una tarea a un sub-agente especializado (architect, coder, tester, doc)."""
    orchestrator = SwarmOrchestrator()
    return orchestrator.spawn_subagent(role, task, context)


def run_swarm_pipeline(task_description: str) -> str:
    """Ejecuta el pipeline completo de desarrollo multi-agente (Architect ➔ Coder ➔ Tester ➔ Doc)."""
    orchestrator = SwarmOrchestrator()
    return orchestrator.run_swarm_pipeline(task_description)


def get_swarm_status() -> str:
    """Obtiene el historial de ejecuciones y estado actual del enjambre multi-agente."""
    orchestrator = SwarmOrchestrator()
    return orchestrator.get_swarm_status()


# -------------------------------------------------------------------------
# 📐 HERRAMIENTAS DE NAVEGACIÓN SEMÁNTICA AST (PASO 2)
# -------------------------------------------------------------------------

def ast_index_repository(directory: str = ".") -> str:
    """Escanea e indexa mediante AST todos los símbolos de un repositorio o carpeta."""
    indexer = ASTCodeIndexer()
    return indexer.index_directory(directory)


def ast_find_definition(symbol_name: str) -> str:
    """Busca la definición exacta (archivo, línea, firma) de un símbolo en el proyecto."""
    indexer = ASTCodeIndexer()
    return indexer.find_definition(symbol_name)


def ast_get_file_outline(filepath: str) -> str:
    """Genera el esquema sintáctico estructurado (clases, funciones, métodos) de un archivo."""
    indexer = ASTCodeIndexer()
    return indexer.get_file_outline(filepath)


def ast_find_references(symbol_name: str, directory: str = ".") -> str:
    """Encuentra todas las apariciones y referencias de uso de un símbolo en el código."""
    indexer = ASTCodeIndexer()
    return indexer.find_references(symbol_name, directory)


# -------------------------------------------------------------------------
# 📚 HERRAMIENTAS DE APARTADO DE FUENTES
# -------------------------------------------------------------------------

def add_source(type_name: str, path_or_url: str, name: str = None) -> str:
    """Indiza una nueva fuente (texto, URL o PDF)."""
    sm = SourcesManager()
    return sm.add_source(type_name, path_or_url, name)


def list_sources() -> str:
    """Muestra el catálogo de fuentes indexadas."""
    sm = SourcesManager()
    return sm.list_sources()


def search_sources(query: str) -> str:
    """Busca en el índice de fuentes la consulta indicada."""
    sm = SourcesManager()
    return sm.search_sources(query)


def remove_source(identifier: str) -> str:
    """Elimina una fuente del índice por su ID o nombre."""
    sm = SourcesManager()
    return sm.remove_source(identifier)


# -------------------------------------------------------------------------
# 🧠 HERRAMIENTAS DE MEMORIA Y PERFIL DE ESTILO
# -------------------------------------------------------------------------

def learn_user_style(pattern: str, category: str = "explicit") -> str:
    """Registra una regla o preferencia de estilo de código observada."""
    mem = UserStyleMemory()
    return mem.learn_preference(pattern, category)


def get_user_style_profile() -> str:
    """Obtiene el resumen formateado del perfil de estilo de programación del usuario."""
    mem = UserStyleMemory()
    return mem.get_profile_summary()


# -------------------------------------------------------------------------
# 🌐 HERRAMIENTAS DEL REGISTRO Y CLIENTE MCP (PASO 1)
# -------------------------------------------------------------------------

def mcp_register_server(
    server_id: str,
    name: str,
    command: str,
    args: str = "",
    transport_type: str = "stdio",
    description: str = ""
) -> str:
    """Registra un nuevo servidor MCP en el catálogo del proyecto."""
    reg = MCPRegistry()
    args_list = [a.strip() for a in args.split() if a.strip()] if args else []
    return reg.register_server(
        server_id=server_id,
        name=name,
        command=command,
        args=args_list,
        transport_type=transport_type,
        description=description
    )


def mcp_list_servers() -> str:
    """Muestra la lista de servidores MCP registrados en el proyecto."""
    reg = MCPRegistry()
    return reg.list_servers()


def mcp_unregister_server(server_id: str) -> str:
    """Elimina un servidor MCP del registro."""
    reg = MCPRegistry()
    return reg.unregister_server(server_id)


# -------------------------------------------------------------------------
# 🔌 HERRAMIENTAS DE GESTOR DE PAQUETES Y PLUGINS
# -------------------------------------------------------------------------

def install_plugin_package(zip_path: str) -> str:
    """Valida e instala un paquete de extensión (.zip)."""
    pm = PluginManager()
    pkg_m = PackageManager(pm)
    return pkg_m.install_from_zip(zip_path)


def list_installed_plugins() -> str:
    """Lista las extensiones activas instaladas en el sistema."""
    pm = PluginManager()
    pm.load_all_plugins()
    if not pm.active_plugins:
        return "No hay extensiones instaladas actualmente en el sistema."
    lines = ["🔌 EXTENSIONES / PLUGINS INSTALADOS:"]
    for pid, info in pm.active_plugins.items():
        m = info["manifest"]
        lines.append(f"• [{pid}] {m.get('name', pid)} v{m.get('version', '1.0.0')} - {m.get('description', '')}")
    return "\n".join(lines)


def uninstall_plugin_package(plugin_id: str) -> str:
    """Desinstala y elimina un paquete de extensión."""
    pm = PluginManager()
    pm.load_all_plugins()
    pkg_m = PackageManager(pm)
    return pkg_m.uninstall_plugin(plugin_id)
