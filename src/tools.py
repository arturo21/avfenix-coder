# -*- coding: utf-8 -*-
import os
import shutil

def make_directory(path: str) -> str:
    """Crea un directorio y sus padres si no existen."""
    try:
        os.makedirs(path, exist_ok=True)
        return f"[Éxito] Directorio creado o ya existente: {path}"
    except Exception as e:
        return f"[Error] No se pudo crear el directorio {path}: {e}"

def list_directory(path: str = ".") -> str:
    """Lista el contenido de un directorio de forma ordenada."""
    try:
        if not os.path.exists(path):
            return f"[Error] El directorio no existe: {path}"
        items = os.listdir(path)
        if not items:
            return f"[Info] El directorio {path} está vacío."
        return f"[Éxito] Contenido de {path}:\n" + "\n".join(f"- {item}" for item in items)
    except Exception as e:
        return f"[Error] No se pudo listar {path}: {e}"

def read_file(path: str) -> str:
    """Lee el contenido de un archivo de texto."""
    try:
        if not os.path.exists(path):
            return f"[Error] El archivo no existe: {path}"
        with open(path, "r", encoding="utf-8") as f:
            content = f.read()
        return f"[Éxito] Contenido de {path}:\n{content}"
    except Exception as e:
        return f"[Error] No se pudo leer {path}: {e}"

def write_file(path: str, content: str) -> str:
    """Crea o escribe un archivo con contenido nuevo, creando carpetas si es necesario."""
    try:
        dir_name = os.path.dirname(path)
        if dir_name:
            os.makedirs(dir_name, exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        return f"[Éxito] Archivo escrito correctamente ({len(content)} caracteres): {path}"
    except Exception as e:
        return f"[Error] No se pudo escribir {path}: {e}"

def patch_file(path: str, search: str, replace: str) -> str:
    """Realiza un reemplazo quirúrgico en un archivo existente (evita reescribir todo)."""
    try:
        if not os.path.exists(path):
            return f"[Error] El archivo no existe para parchar: {path}"
        with open(path, "r", encoding="utf-8") as f:
            content = f.read()
        if search not in content:
            return f"[Error] No se encontró el bloque a reemplazar en {path}."
        new_content = content.replace(search, replace, 1)
        with open(path, "w", encoding="utf-8") as f:
            f.write(new_content)
        return f"[Éxito] Archivo parchado correctamente: {path}"
    except Exception as e:
        return f"[Error] Fallo al parchar {path}: {e}"

def move_file(source: str, destination: str) -> str:
    """Mueve o renombra un archivo o directorio."""
    try:
        shutil.move(source, destination)
        return f"[Éxito] Movido/renombrado de {source} a {destination}"
    except Exception as e:
        return f"[Error] No se pudo mover {source}: {e}"