# -*- coding: utf-8 -*-
"""
system_access_tool.py - Herramienta de Acceso al Sistema de Archivos y Control de Rutas

Permite crear directorios, crear y modificar archivos en rutas específicas (absolutas o relativas),
ejecutar comandos del sistema operativo y consultar el estado y metadatos de rutas en el sistema.
Diseñado para integrarse como herramienta nativa o plugin en AVFenix Coder.
"""

import os
import shutil
import subprocess
from datetime import datetime
from typing import Dict, Any, Optional, List


class SystemAccessTool:
    """
    Gestor de acceso al sistema de archivos y ejecución de comandos.
    Permite operar de forma segura sobre cualquier ruta especificada.
    """

    def __init__(self, base_directory: Optional[str] = None):
        self.base_dir = os.path.abspath(base_directory) if base_directory else os.getcwd()

    def set_target_directory(self, target_path: str) -> Dict[str, Any]:
        """
        Establece y valida la ruta de trabajo objetivo en el sistema.
        Si la ruta no existe, la crea automáticamente.
        """
        try:
            abs_path = os.path.abspath(target_path)
            os.makedirs(abs_path, exist_ok=True)
            self.base_dir = abs_path
            return {
                "success": True,
                "message": f"Directorio de trabajo establecido en: {abs_path}",
                "path": abs_path
            }
        except Exception as e:
            return {
                "success": False,
                "message": f"Error al acceder/crear el directorio '{target_path}': {e}",
                "path": target_path
            }

    def create_directory(self, path: str) -> Dict[str, Any]:
        """
        Crea una carpeta o estructura de carpetas anidadas en la ruta especificada.
        Acepta rutas absolutas o relativas al directorio objetivo.
        """
        try:
            full_path = path if os.path.isabs(path) else os.path.join(self.base_dir, path)
            abs_path = os.path.abspath(full_path)
            os.makedirs(abs_path, exist_ok=True)
            return {
                "success": True,
                "message": f"Carpeta creada exitosamente en: '{abs_path}'",
                "path": abs_path
            }
        except Exception as e:
            return {
                "success": False,
                "message": f"Error al crear la carpeta '{path}': {e}",
                "path": path
            }

    def create_file(self, path: str, content: str = "", overwrite: bool = True) -> Dict[str, Any]:
        """
        Crea o actualiza un archivo en la ruta especificada, creando carpetas padres si no existen.
        
        :param path: Ruta del archivo (absoluta o relativa)
        :param content: Contenido de texto a escribir en el archivo
        :param overwrite: Si es False y el archivo existe, no lo sobrescribe
        """
        try:
            full_path = path if os.path.isabs(path) else os.path.join(self.base_dir, path)
            abs_path = os.path.abspath(full_path)
            
            if os.path.exists(abs_path) and not overwrite:
                return {
                    "success": False,
                    "message": f"El archivo ya existe y overwrite=False: '{abs_path}'",
                    "path": abs_path
                }

            parent_dir = os.path.dirname(abs_path)
            if parent_dir:
                os.makedirs(parent_dir, exist_ok=True)

            with open(abs_path, "w", encoding="utf-8") as f:
                f.write(content)

            char_count = len(content)
            line_count = len(content.splitlines())

            return {
                "success": True,
                "message": f"Archivo creado/escrito exitosamente ({char_count} caracteres, {line_count} líneas) en: '{abs_path}'",
                "path": abs_path,
                "size_bytes": os.path.getsize(abs_path)
            }
        except Exception as e:
            return {
                "success": False,
                "message": f"Error al crear el archivo '{path}': {e}",
                "path": path
            }

    def execute_command(self, command: str, working_dir: Optional[str] = None, timeout: int = 60) -> Dict[str, Any]:
        """
        Ejecuta un comando en la consola del sistema operativo en el directorio especificado.
        """
        try:
            target_dir = working_dir if working_dir else self.base_dir
            res = subprocess.run(
                command,
                shell=True,
                cwd=target_dir,
                capture_output=True,
                text=True,
                timeout=timeout
            )
            return {
                "success": res.returncode == 0,
                "exit_code": res.returncode,
                "stdout": res.stdout.strip(),
                "stderr": res.stderr.strip(),
                "working_dir": target_dir
            }
        except subprocess.TimeoutExpired:
            return {
                "success": False,
                "message": f"El comando superó el tiempo límite de {timeout} segundos.",
                "working_dir": working_dir or self.base_dir
            }
        except Exception as e:
            return {
                "success": False,
                "message": f"Error al ejecutar el comando: {e}",
                "working_dir": working_dir or self.base_dir
            }

    def list_directory(self, path: Optional[str] = None) -> Dict[str, Any]:
        """
        Lista el contenido del directorio especificado (o del directorio base si se omite).
        """
        try:
            target_path = path if path else self.base_dir
            full_path = target_path if os.path.isabs(target_path) else os.path.join(self.base_dir, target_path)
            abs_path = os.path.abspath(full_path)

            if not os.path.exists(abs_path):
                return {"success": False, "message": f"La ruta '{abs_path}' no existe."}

            items = []
            for entry in sorted(os.listdir(abs_path)):
                entry_path = os.path.join(abs_path, entry)
                is_dir = os.path.isdir(entry_path)
                stats = os.stat(entry_path)
                items.append({
                    "name": entry,
                    "type": "directory" if is_dir else "file",
                    "size_bytes": stats.st_size if not is_dir else 0,
                    "modified": datetime.fromtimestamp(stats.st_mtime).strftime("%Y-%m-%d %H:%M:%S")
                })

            return {
                "success": True,
                "path": abs_path,
                "total_items": len(items),
                "items": items
            }
        except Exception as e:
            return {
                "success": False,
                "message": f"Error al listar el directorio '{path}': {e}"
            }


# -------------------------------------------------------------------------
# 🔌 ADAPTADOR DE HERRAMIENTA PARA AVFENIX CODER (Etiquetas XML)
# -------------------------------------------------------------------------

_tool_instance = SystemAccessTool()

def handle_create_folder(path: str) -> str:
    """Manejador XML: <create_folder path="ruta/destino"/>"""
    res = _tool_instance.create_directory(path)
    return res["message"]

def handle_create_file(path: str, content: str = "") -> str:
    """Manejador XML: <create_file path="ruta/archivo.ext">contenido</create_file>"""
    res = _tool_instance.create_file(path, content)
    return res["message"]


# Ejemplo de uso rápido
if __name__ == "__main__":
    tool = SystemAccessTool()
    print("=== Probando SystemAccessTool ===")
    
    # 1. Crear carpeta
    res_folder = tool.create_directory("/tmp/mi_nuevo_proyecto/src")
    print(res_folder["message"])
    
    # 2. Crear archivo
    res_file = tool.create_file(
        "/tmp/mi_nuevo_proyecto/src/app.py",
        "print('¡Hola desde la nueva app creada por SystemAccessTool!')\n"
    )
    print(res_file["message"])
    
    # 3. Listar directorio
    res_list = tool.list_directory("/tmp/mi_nuevo_proyecto")
    print(f"Contenido de /tmp/mi_nuevo_proyecto: {res_list['total_items']} elementos.")
