# -*- coding: utf-8 -*-
"""
tui_enrichment_manager.py - Enriquecimiento de la Interfaz TUI (Paso 6) para AVFenix Coder.
Proporciona utilidades para la integración de widgets avanzados: Árbol de Directorios (DirectoryTree),
Visor Markdown (MarkdownViewer / Rich Render), Tablas de Estado (DataTable) y Renderizador de Diffs.
"""

import os
import re
import json
import logging
from typing import Dict, Any, List, Optional

logger = logging.getLogger("TUIEnrichmentManager")


class TUIEnrichmentManager:
    """
    Gestor encargado del enriquecimiento visual de la interfaz TUI.
    Genera estructuras de datos y vistas renderizadas en Rich/Textual
    para directorios, markdown, tablas de métricas y diffs.
    """

    def __init__(self, base_dir: str = "."):
        self.base_dir = os.path.abspath(base_dir)

    def render_directory_tree(self, path: str = ".") -> str:
        """
        Genera una vista en árbol estructurada e interactiva para la exploración de directorios.
        """
        abs_path = os.path.abspath(os.path.join(self.base_dir, path))
        if not os.path.exists(abs_path):
            return f"Error: El directorio '{path}' no existe en el sistema de archivos."

        ignore_dirs = {".git", "__pycache__", ".venv", "venv", "node_modules", ".pytest_cache", ".avfenix_backups", ".avfenix_sources", ".avfenix_mcp", ".avfenix_ast", ".avfenix_swarm", ".avfenix_sandbox", ".avfenix_memory"}
        
        tree_lines = [f"📂 [bold cyan]{os.path.basename(abs_path) or abs_path}[/bold cyan]"]

        def _build_nodes(current_dir, prefix="", depth=1, max_depth=4):
            if depth > max_depth:
                return
            try:
                entries = sorted(os.listdir(current_dir))
            except PermissionError:
                return

            filtered = [e for e in entries if e not in ignore_dirs]
            total = len(filtered)

            for idx, entry in enumerate(filtered):
                is_last = (idx == total - 1)
                connector = "└── " if is_last else "├── "
                full = os.path.join(current_dir, entry)

                if os.path.isdir(full):
                    sub_count = sum(1 for item in os.listdir(full) if item not in ignore_dirs) if os.access(full, os.R_OK) else 0
                    tree_lines.append(f"{prefix}{connector}📁 [bold yellow]{entry}/[/bold yellow] [dim]({sub_count} items)[/dim]")
                    new_pref = prefix + ("    " if is_last else "│   ")
                    _build_nodes(full, new_pref, depth + 1, max_depth)
                else:
                    ext = os.path.splitext(entry)[1].lower()
                    icon = "🐍" if ext == ".py" else ("📄" if ext in (".md", ".txt") else ("⚙️" if ext in (".json", ".yaml", ".yml", ".env") else "📄"))
                    sz = os.path.getsize(full) if os.path.exists(full) else 0
                    tree_lines.append(f"{prefix}{connector}{icon} [cyan]{entry}[/cyan] [dim]({sz} B)[/dim]")

        _build_nodes(abs_path)
        return "\n".join(tree_lines)

    def preview_file(self, filepath: str, max_lines: int = 150) -> str:
        """
        Genera una vista previa formateada con información de metadatos y contenido de archivo.
        """
        abs_path = os.path.abspath(os.path.join(self.base_dir, filepath))
        if not os.path.exists(abs_path):
            return f"Error: El archivo '{filepath}' no existe."

        try:
            stat = os.stat(abs_path)
            size = stat.st_size
            ext = os.path.splitext(filepath)[1].lower() or "text"

            with open(abs_path, "r", encoding="utf-8", errors="ignore") as f:
                lines = f.readlines()

            total_lines = len(lines)
            shown_lines = lines[:max_lines]
            code_block = "".join(shown_lines)

            output = [
                f"📄 [bold cyan]VISOR PREVIO DE ARCHIVO: {filepath}[/bold cyan]",
                f"• Tamaño: {size} bytes | Líneas Totales: {total_lines} | Formato: {ext.upper()}",
                "─" * 60,
                f"```{ext.replace('.', '')}\n{code_block.rstrip()}\n```"
            ]

            if total_lines > max_lines:
                output.append(f"\n[dim]... (Mostrando las primeras {max_lines} de {total_lines} líneas)[/dim]")

            return "\n".join(output)
        except Exception as e:
            return f"Error al generar vista previa de '{filepath}': {e}"

    def render_markdown_formatted(self, path_or_content: str) -> str:
        """
        Formatea y renderiza un documento Markdown o texto estructurado para visualización de alta calidad.
        """
        content = path_or_content
        source_label = "Texto Directo"

        abs_path = os.path.abspath(os.path.join(self.base_dir, path_or_content))
        if os.path.exists(abs_path) and os.path.isfile(abs_path):
            source_label = path_or_content
            try:
                with open(abs_path, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
            except Exception as e:
                return f"Error al leer archivo Markdown '{path_or_content}': {e}"

        header = f"📝 [bold green]VISOR DE DOCUMENTACIÓN MARKDOWN ({source_label})[/bold green]\n" + "═" * 60 + "\n"
        return header + content

    def render_status_table(self, category: str = "all") -> str:
        """
        Genera una vista de tabla enriquecida de métricas y estado del sistema (MCP, Swarm, AST, Memoria, Sandbox).
        """
        cat = category.lower().strip()
        lines = [f"📊 [bold cyan]TABLA DE ESTADO Y MÉTRICAS DEL AGENTE ({cat.upper()})[/bold cyan]", "═" * 60]

        # 1. MCP Status
        if cat in ("mcp", "all"):
            mcp_file = os.path.join(self.base_dir, ".avfenix_mcp", "servers.json")
            mcp_count = 0
            if os.path.exists(mcp_file):
                try:
                    with open(mcp_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    mcp_count = len(data.get("servers", {}))
                except Exception:
                    pass
            lines.append(f"🌐 [bold]Servidores MCP Registrados:[/bold] {mcp_count} servidores activos")

        # 2. AST Status
        if cat in ("ast", "all"):
            ast_file = os.path.join(self.base_dir, ".avfenix_ast", "symbol_index.json")
            files_c = 0
            sym_c = 0
            if os.path.exists(ast_file):
                try:
                    with open(ast_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    files_c = len(data.get("files", {}))
                    sym_c = len(data.get("symbols", {}))
                except Exception:
                    pass
            lines.append(f"📐 [bold]Índice Sintáctico AST:[/bold] {files_c} archivos | {sym_c} símbolos indexados")

        # 3. Swarm Status
        if cat in ("swarm", "all"):
            swarm_file = os.path.join(self.base_dir, ".avfenix_swarm", "execution_log.json")
            exec_c = 0
            if os.path.exists(swarm_file):
                try:
                    with open(swarm_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    exec_c = len(data.get("executions", []))
                except Exception:
                    pass
            lines.append(f"🤖 [bold]Enjambre Multi-Agente (Swarm):[/bold] {exec_c} iteraciones registradas")

        # 4. Style Memory Status
        if cat in ("memory", "style", "all"):
            mem_file = os.path.join(self.base_dir, ".avfenix_memory", "user_coding_profile.json")
            rules_c = 0
            if os.path.exists(mem_file):
                try:
                    with open(mem_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    rules_c = len(data.get("explicit_rules", []))
                except Exception:
                    pass
            lines.append(f"🧠 [bold]Memoria de Estilo de Usuario:[/bold] {rules_c} reglas de perfil aprendidas")

        # 5. Sandbox Audit Status
        if cat in ("sandbox", "all"):
            sb_file = os.path.join(self.base_dir, ".avfenix_sandbox", "audit_log.json")
            audit_c = 0
            if os.path.exists(sb_file):
                try:
                    with open(sb_file, "r", encoding="utf-8") as f:
                        data = json.load(f)
                    audit_c = len(data)
                except Exception:
                    pass
            lines.append(f"🛡️ [bold]Sandbox Auditoría:[/bold] {audit_c} ejecuciones seguras registradas")

        lines.append("─" * 60)
        return "\n".join(lines)
