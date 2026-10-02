# -*- coding: utf-8 -*-
"""
code_ast_indexer.py - Motor de Navegación Semántica de Código e Indización AST para AVFenix Coder.
Analiza la estructura sintáctica de código (Python AST + Parsers de Símbolos Multilenguaje)
para permitir localización de definiciones, esquema de archivos, grafo de llamadas y referencias.
"""

import os
import ast
import re
import json
import logging
from datetime import datetime
from typing import Dict, Any, List, Optional

logger = logging.getLogger("ASTCodeIndexer")

AST_DIR = ".avfenix_ast"
INDEX_FILE = os.path.join(AST_DIR, "symbol_index.json")


class ASTCodeIndexer:
    """
    Motor de análisis e indización sintáctica AST para navegación de código de alto nivel.
    """

    def __init__(self, base_dir: str = "."):
        self.base_dir = os.path.abspath(base_dir)
        self.ast_dir = os.path.join(self.base_dir, AST_DIR)
        self.index_file = os.path.join(self.base_dir, INDEX_FILE)
        self._ensure_storage()

    def _ensure_storage(self):
        """Crea el directorio de almacenamiento del índice AST si no existe."""
        os.makedirs(self.ast_dir, exist_ok=True)
        if not os.path.exists(self.index_file):
            self._save_index({"symbols": {}, "files": {}, "updated_at": ""})

    def _load_index(self) -> Dict[str, Any]:
        """Carga el índice de símbolos desde el archivo JSON."""
        try:
            if not os.path.exists(self.index_file):
                return {"symbols": {}, "files": {}, "updated_at": ""}
            with open(self.index_file, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception as e:
            logger.error(f"Error al cargar índice AST: {e}")
            return {"symbols": {}, "files": {}, "updated_at": ""}

    def _save_index(self, data: Dict[str, Any]):
        """Guarda el índice AST de símbolos en disco de forma atómica."""
        try:
            temp_file = f"{self.index_file}.tmp"
            with open(temp_file, "w", encoding="utf-8") as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            os.replace(temp_file, self.index_file)
        except Exception as e:
            logger.error(f"Error al guardar índice AST: {e}")

    def index_directory(self, target_directory: str = ".") -> str:
        """
        Escanea e indexa mediante AST y análisis sintáctico todos los archivos del directorio target.
        """
        abs_target = os.path.abspath(os.path.join(self.base_dir, target_directory))
        if not os.path.exists(abs_target):
            return f"Error: El directorio '{target_directory}' no existe."

        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_ast"}
        
        index_data = {"symbols": {}, "files": {}, "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
        file_count = 0
        symbol_count = 0

        for root, dirs, files in os.walk(abs_target):
            dirs[:] = [d for d in dirs if d not in ignore_dirs]
            for file in files:
                ext = os.path.splitext(file)[1].lower()
                if ext in (".py", ".js", ".ts", ".php", ".cs", ".java", ".cpp", ".c", ".h"):
                    full_path = os.path.join(root, file)
                    rel_path = os.path.relpath(full_path, self.base_dir)
                    
                    file_symbols = self._parse_file(full_path, rel_path, ext)
                    index_data["files"][rel_path] = {
                        "extension": ext,
                        "symbol_names": [s["name"] for s in file_symbols]
                    }
                    
                    for sym in file_symbols:
                        sym_name = sym["name"]
                        if sym_name not in index_data["symbols"]:
                            index_data["symbols"][sym_name] = []
                        index_data["symbols"][sym_name].append(sym)
                        symbol_count += 1
                    
                    file_count += 1

        self._save_index(index_data)
        return (
            f"Éxito: Indización AST completada en '{target_directory}'.\n"
            f"- Archivos analizados: {file_count}\n"
            f"- Símbolos (clases, funciones, métodos) indexados: {symbol_count}\n"
            f"- Índice guardado en '{self.index_file}'"
        )

    def _parse_file(self, full_path: str, rel_path: str, ext: str) -> List[Dict[str, Any]]:
        """Aplica análisis AST o regex estructural dependiendo del lenguaje del archivo."""
        symbols = []
        try:
            with open(full_path, "r", encoding="utf-8", errors="ignore") as f:
                code_content = f.read()
        except Exception:
            return symbols

        if ext == ".py":
            return self._parse_python_ast(code_content, rel_path)
        else:
            return self._parse_generic_code(code_content, rel_path, ext)

    def _parse_python_ast(self, code_content: str, rel_path: str) -> List[Dict[str, Any]]:
        """Analiza sintácticamente código Python usando la librería estándar ast."""
        symbols = []
        try:
            tree = ast.parse(code_content)
        except SyntaxError:
            # Fallback a regex si el código Python tiene errores sintácticos parciales
            return self._parse_generic_code(code_content, rel_path, ".py")

        for node in ast.walk(tree):
            if isinstance(node, ast.ClassDef):
                doc = ast.get_docstring(node) or ""
                bases = [self._ast_unparse(b) for b in node.bases]
                symbols.append({
                    "name": node.name,
                    "kind": "class",
                    "file": rel_path,
                    "line": node.lineno,
                    "docstring": doc.split("\n")[0] if doc else "",
                    "signature": f"class {node.name}({', '.join(bases)})" if bases else f"class {node.name}"
                })

            elif isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)):
                doc = ast.get_docstring(node) or ""
                args = [arg.arg for arg in node.args.args]
                async_prefix = "async " if isinstance(node, ast.AsyncFunctionDef) else ""
                
                # Determinar si es método de clase o función independiente
                kind = "function"
                if hasattr(node, "parent_class"):
                    kind = "method"

                symbols.append({
                    "name": node.name,
                    "kind": kind,
                    "file": rel_path,
                    "line": node.lineno,
                    "docstring": doc.split("\n")[0] if doc else "",
                    "signature": f"{async_prefix}def {node.name}({', '.join(args)})"
                })

        return symbols

    def _ast_unparse(self, node) -> str:
        """Convierte nodos AST de expresiones de tipo a string."""
        if hasattr(ast, "unparse"):
            return ast.unparse(node)
        if isinstance(node, ast.Name):
            return node.id
        if isinstance(node, ast.Attribute):
            return f"{self._ast_unparse(node.value)}.{node.attr}"
        return "object"

    def _parse_generic_code(self, code_content: str, rel_path: str, ext: str) -> List[Dict[str, Any]]:
        """Extrae símbolos de lenguajes como JS, PHP, C#, Java o C++ mediante regex estricto."""
        symbols = []
        lines = code_content.splitlines()

        patterns = [
            (r'^\s*(?:export\s+)?class\s+([A-Za-z0-9_]+)', "class"),
            (r'^\s*(?:export\s+)?(?:async\s+)?function\s+([A-Za-z0-9_]+)\s*\(', "function"),
            (r'^\s*(?:public|private|protected|static|\s)*function\s+([A-Za-z0-9_]+)\s*\(', "function"),
            (r'^\s*(?:public|private|protected|static|\s)+(?:async\s+)?[A-Za-z0-9_<>]+\s+([A-Za-z0-9_]+)\s*\(', "method"),
        ]

        for idx, line in enumerate(lines, 1):
            for pat, kind in patterns:
                m = re.search(pat, line)
                if m:
                    sym_name = m.group(1)
                    if sym_name not in ("if", "for", "while", "switch", "catch"):
                        symbols.append({
                            "name": sym_name,
                            "kind": kind,
                            "file": rel_path,
                            "line": idx,
                            "docstring": "",
                            "signature": line.strip()[:100]
                        })
                    break

        return symbols

    def find_definition(self, symbol_name: str) -> str:
        """Busca y ubica la definición exacta de una clase, función o símbolo."""
        index_data = self._load_index()
        symbols = index_data.get("symbols", {})

        matches = symbols.get(symbol_name, [])
        if not matches:
            # Búsqueda insensible a mayúsculas como fallback
            for sym_key, sym_list in symbols.items():
                if sym_key.lower() == symbol_name.lower():
                    matches = sym_list
                    break

        if not matches:
            return f"No se encontró la definición del símbolo '{symbol_name}' en el índice AST. Ejecuta <ast_index_repository/> para actualizar."

        output = [f"📍 DEFINICIONES DE SÍMBOLO ENCONTRADAS PARA '{symbol_name}':\n"]
        for sym in matches:
            output.append(
                f"• [{sym['kind'].upper()}] {sym['name']}\n"
                f"  Firma: `{sym['signature']}`\n"
                f"  Ubicación: `{sym['file']}:{sym['line']}`"
            )
            if sym.get("docstring"):
                output.append(f"  Docstring: \"{sym['docstring']}\"")
            output.append("---")

        return "\n".join(output)

    def get_file_outline(self, filepath: str) -> str:
        """Genera el esquema estructurado (clases, métodos y firmas) de un archivo específico."""
        index_data = self._load_index()
        rel_path = os.path.relpath(filepath, self.base_dir) if os.path.isabs(filepath) else filepath

        file_info = index_data.get("files", {}).get(rel_path)
        if not file_info:
            # Forzar re-parseo si no está en el índice
            full_path = os.path.join(self.base_dir, rel_path)
            if not os.path.exists(full_path):
                return f"Error: El archivo '{filepath}' no existe."
            ext = os.path.splitext(rel_path)[1].lower()
            symbols = self._parse_file(full_path, rel_path, ext)
        else:
            symbols = []
            for sym_list in index_data.get("symbols", {}).values():
                for sym in sym_list:
                    if sym.get("file") == rel_path:
                        symbols.append(sym)

        if not symbols:
            return f"El archivo '{rel_path}' no contiene símbolos AST reconocibles o no ha sido indexado."

        symbols.sort(key=lambda x: x["line"])
        output = [f"📐 ESQUEMA AST DE SÍMBOLOS PARA '{rel_path}':\n"]
        for sym in symbols:
            output.append(f"• [Línea {sym['line']:3d}] {sym['kind'].upper()}: `{sym['signature']}`")
            if sym.get("docstring"):
                output.append(f"  └─ Doc: {sym['docstring']}")

        return "\n".join(output)

    def find_references(self, symbol_name: str, target_dir: str = ".") -> str:
        """Encuentra todas las apariciones y referencias de invocación de un símbolo en el código."""
        abs_target = os.path.abspath(os.path.join(self.base_dir, target_dir))
        if not os.path.exists(abs_target):
            return f"Error: El directorio '{target_dir}' no existe."

        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_ast"}
        references = []

        pattern = re.compile(r'\b' + re.escape(symbol_name) + r'\b')

        for root, dirs, files in os.walk(abs_target):
            dirs[:] = [d for d in dirs if d not in ignore_dirs]
            for file in files:
                ext = os.path.splitext(file)[1].lower()
                if ext in (".py", ".js", ".ts", ".php", ".cs", ".java", ".cpp", ".c", ".h"):
                    full_path = os.path.join(root, file)
                    rel_path = os.path.relpath(full_path, self.base_dir)
                    try:
                        with open(full_path, "r", encoding="utf-8", errors="ignore") as f:
                            for idx, line in enumerate(f, 1):
                                if pattern.search(line):
                                    references.append(f"• `{rel_path}:{idx}` ➔ {line.strip()[:100]}")
                    except Exception:
                        continue

        if not references:
            return f"No se encontraron referencias para el símbolo '{symbol_name}' en '{target_dir}'."

        if len(references) > 80:
            return f"🔍 SE ENCONTRARON {len(references)} REFERENCIAS PARA '{symbol_name}' (Mostrando las primeras 80):\n\n" + "\n".join(references[:80])

        return f"🔍 REFERENCIAS ENCONTRADAS PARA SÍMBOLO '{symbol_name}' ({len(references)}):\n\n" + "\n".join(references)
