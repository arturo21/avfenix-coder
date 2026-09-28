# -*- coding: utf-8 -*-
import os
import shutil

def read_file(path: str) -> str:
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."
        with open(path, "r", encoding="utf-8") as f:
            return f.read()
    except Exception as e:
        return f"Error al leer el archivo: {e}"

def write_file(path: str, content: str) -> str:
    try:
        dir_name = os.path.dirname(path)
        if dir_name and not os.path.exists(dir_name):
            os.makedirs(dir_name, exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        return f"Éxito: Archivo '{path}' creado/escrito correctamente."
    except Exception as e:
        return f"Error al escribir el archivo: {e}"

def patch_file(path: str, search: str, replace: str) -> str:
    try:
        if not os.path.exists(path):
            return f"Error: El archivo '{path}' no existe."
        with open(path, "r", encoding="utf-8") as f:
            content = f.read()
        if search not in content:
            return f"Error: No se encontró el texto a reemplazar en '{path}'."
        updated_content = content.replace(search, replace, 1)
        with open(path, "w", encoding="utf-8") as f:
            f.write(updated_content)
        return f"Éxito: Archivo '{path}' modificado quirúrgicamente."
    except Exception as e:
        return f"Error al modificar el archivo: {e}"

def make_directory(path: str) -> str:
    try:
        os.makedirs(path, exist_ok=True)
        return f"Éxito: Directorio '{path}' creado correctamente."
    except Exception as e:
        return f"Error al crear el directorio: {e}"

def list_directory(path: str = ".") -> str:
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
    try:
        if not os.path.exists(source):
            return f"Error: El origen '{source}' no existe."
        dest_dir = os.path.dirname(destination)
        if dest_dir and not os.path.exists(dest_dir):
            os.makedirs(dest_dir, exist_ok=True)
        shutil.move(source, destination)
        return f"Éxito: '{source}' movido a '{destination}'."
    except Exception as e:
        return f"Error al mover/renombrar: {e}"
