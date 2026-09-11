# -*- coding: utf-8 -*-
import os
import shutil
import subprocess
import fnmatch
import time
from datetime import datetime
import urllib.request
import re

# =========================================================================
# 🛠️ HERRAMIENTAS NATIVAS DE DISCO Y SISTEMA (16 HERRAMIENTAS)
# =========================================================================

def read_file(path: str) -> str:
    """Lee el contenido de un archivo de texto."""
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."
        with open(path, "r", encoding="utf-8") as f:
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
        with open(path, "r", encoding="utf-8") as f:
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

# -------------------------------------------------------------------------
# 🚀 NUEVAS 10 HERRAMIENTAS AVANZADAS
# -------------------------------------------------------------------------

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
        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups"}
        
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
        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups"}
        
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
    """Ejecuta pruebas unitarias usando pytest o unittest."""
    try:
        result = subprocess.run(
            ["python3", "-m", "pytest", test_path, "-v"],
            text=True,
            capture_output=True,
            timeout=60
        )
        if result.returncode in (0, 1):
            return f"--- Resultados de Pytest ---\n{result.stdout}\n{result.stderr}".strip()
            
        result_unittest = subprocess.run(
            ["python3", "-m", "unittest", "discover", "-s", test_path],
            text=True,
            capture_output=True,
            timeout=60
        )
        return f"--- Resultados de Unittest ---\n{result_unittest.stdout}\n{result_unittest.stderr}".strip()
    except Exception as e:
        return f"Error al ejecutar pruebas: {e}"

def git_status(directory: str = ".") -> str:
    """Consulta el estado del repositorio Git en la carpeta indicada."""
    try:
        result = subprocess.run(
            ["git", "status", "-s"],
            cwd=directory,
            text=True,
            capture_output=True,
            timeout=10
        )
        if result.returncode != 0:
            return f"Error: No es un repositorio Git válido o fallo en comando: {result.stderr.strip()}"
        output = result.stdout.strip()
        if not output:
            return "Git Status: El árbol de trabajo está limpio (sin cambios pendientes)."
        return f"--- Git Status ---\n{output}"
    except Exception as e:
        return f"Error al consultar git status: {e}"

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
            
        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups"}
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