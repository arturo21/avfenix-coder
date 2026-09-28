# -*- coding: utf-8 -*-
"""
sources_manager.py - Gestor de Fuentes e Indización de Documentación para AVFenix Coder.
Soporta indización y búsqueda en URLs/Web (ej. WordPress Codex), Archivos PDF y Texto/Markdown.
"""

import os
import re
import json
import shutil
import urllib.request
import urllib.parse
import subprocess
from datetime import datetime
from typing import Dict, Any, List, Optional

SOURCES_DIR = ".avfenix_sources"
INDEX_FILE = os.path.join(SOURCES_DIR, "index.json")
CONTENT_DIR = os.path.join(SOURCES_DIR, "content")


class SourcesManager:
    """
    Administra la ingesta, almacenamiento, búsqueda y consulta de fuentes
    de documentación indexadas (Texto, URLs/Web, PDFs).
    """

    def __init__(self, base_dir: str = "."):
        self.base_dir = base_dir
        self.sources_dir = os.path.join(base_dir, SOURCES_DIR)
        self.index_file = os.path.join(base_dir, INDEX_FILE)
        self.content_dir = os.path.join(base_dir, CONTENT_DIR)
        self._ensure_storage()

    def _ensure_storage(self):
        """Crea la estructura de carpetas para el almacenamiento de fuentes."""
        os.makedirs(self.sources_dir, exist_ok=True)
        os.makedirs(self.content_dir, exist_ok=True)
        if not os.path.exists(self.index_file):
            self._save_index([])

    def _load_index(self) -> List[Dict[str, Any]]:
        """Carga el índice de fuentes desde el archivo JSON."""
        try:
            if not os.path.exists(self.index_file):
                return []
            with open(self.index_file, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return []

    def _save_index(self, index_data: List[Dict[str, Any]]):
        """Guarda el índice de fuentes en el archivo JSON."""
        try:
            with open(self.index_file, "w", encoding="utf-8") as f:
                json.dump(index_data, f, ensure_ascii=False, indent=2)
        except Exception:
            pass

    def add_source(
        self,
        source_type: str,
        path_or_url: str,
        name: str = "",
        direct_text: str = ""
    ) -> str:
        """
        Agrega e indexa una nueva fuente (url, pdf, text).

        :param source_type: 'url', 'pdf', o 'text'
        :param path_or_url: URL de la web o ruta al archivo local
        :param name: Nombre descriptivo asignado a la fuente
        :param direct_text: Texto directo si no se pasa archivo
        """
        source_type = source_type.lower().strip()
        if source_type not in ("url", "pdf", "text", "markdown", "md"):
            return f"Error: Tipo de fuente '{source_type}' no soportado. Usa 'url', 'pdf' o 'text'."

        extracted_text = ""
        source_location = path_or_url.strip()

        # 1. Extracción según el tipo de fuente
        if source_type == "url":
            if not (source_location.startswith("http://") or source_location.startswith("https://")):
                source_location = "https://" + source_location
            extracted_text = self._fetch_url_content(source_location)
            if extracted_text.startswith("Error"):
                return extracted_text
            if not name:
                name = f"Web: {urllib.parse.urlparse(source_location).netloc}"

        elif source_type == "pdf":
            if not os.path.exists(source_location):
                return f"Error: El archivo PDF '{source_location}' no existe."
            extracted_text = self._extract_pdf_text(source_location)
            if extracted_text.startswith("Error"):
                return extracted_text
            if not name:
                name = f"PDF: {os.path.basename(source_location)}"

        elif source_type in ("text", "markdown", "md"):
            if direct_text:
                extracted_text = direct_text
            elif os.path.exists(source_location):
                try:
                    with open(source_location, "r", encoding="utf-8", errors="ignore") as f:
                        extracted_text = f.read()
                except Exception as e:
                    return f"Error al leer archivo de texto: {e}"
            else:
                extracted_text = source_location  # Se asume que la cadena entregada es el texto
            if not name:
                name = f"Texto: {name or 'Documento de Referencia'}"

        if not extracted_text or len(extracted_text.strip()) == 0:
            return "Error: No se pudo extraer contenido de texto de la fuente indicada."

        # 2. Guardado del contenido e indexación
        index = self._load_index()
        source_id = f"src_{len(index) + 1:03d}_{int(datetime.now().timestamp())}"
        content_filename = f"{source_id}.txt"
        content_path = os.path.join(self.content_dir, content_filename)

        try:
            with open(content_path, "w", encoding="utf-8") as f:
                f.write(extracted_text)
        except Exception as e:
            return f"Error al guardar contenido indexado: {e}"

        char_count = len(extracted_text)
        word_count = len(extracted_text.split())

        entry = {
            "id": source_id,
            "name": name or f"Fuente {source_id}",
            "type": source_type,
            "location": source_location,
            "content_file": content_filename,
            "char_count": char_count,
            "word_count": word_count,
            "added_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }

        # Evitar duplicados por nombre
        index = [item for item in index if item["name"] != entry["name"]]
        index.append(entry)
        self._save_index(index)

        return (
            f"Éxito: Fuente '{entry['name']}' indexada correctamente.\n"
            f"- Tipo: {source_type.upper()}\n"
            f"- Ubicación/Origen: {source_location}\n"
            f"- Volumen: {word_count} palabras ({char_count} caracteres)\n"
            f"- ID de Índice: {source_id}"
        )

    def _fetch_url_content(self, url: str) -> str:
        """Descarga e indexa texto limpio de una página web o documentación."""
        try:
            req = urllib.request.Request(
                url,
                headers={"User-Agent": "AVFenixCoder-Indexer/1.0 (Python Documentation Agent)"}
            )
            with urllib.request.urlopen(req, timeout=15) as response:
                html = response.read().decode("utf-8", errors="ignore")

            # Limpieza básica de HTML
            text = re.sub(r"<script.*?>.*?</script>", "", html, flags=re.DOTALL | re.IGNORECASE)
            text = re.sub(r"<style.*?>.*?</style>", "", text, flags=re.DOTALL | re.IGNORECASE)
            text = re.sub(r"<.*?>", " ", text)
            lines = (line.strip() for line in text.splitlines())
            chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
            clean_text = "\n".join(chunk for chunk in chunks if chunk)
            return clean_text if clean_text else "Sin contenido de texto extraíble."
        except Exception as e:
            return f"Error al descargar la URL '{url}': {e}"

    def _extract_pdf_text(self, pdf_path: str) -> str:
        """Extrae texto de un archivo PDF usando pdftotext CLI o pypdf como fallback."""
        # Intento 1: pdftotext CLI
        try:
            res = subprocess.run(
                ["pdftotext", pdf_path, "-"],
                capture_output=True,
                text=True,
                timeout=15
            )
            if res.returncode == 0 and res.stdout.strip():
                return res.stdout.strip()
        except Exception:
            pass

        # Intento 2: pypdf / pypdf2 si está instalado
        try:
            import pypdf
            reader = pypdf.PdfReader(pdf_path)
            pages_text = []
            for page in reader.pages:
                t = page.extract_text()
                if t:
                    pages_text.append(t)
            if pages_text:
                return "\n".join(pages_text)
        except Exception:
            pass

        return f"Error: No se pudo extraer texto del PDF '{pdf_path}'. Instala 'pdftotext' o 'pypdf'."

    def list_sources(self) -> str:
        """Lista las fuentes indexadas actualmente en el proyecto."""
        index = self._load_index()
        if not index:
            return "No hay fuentes indexadas en este proyecto. Usa <add_source/> para registrar documentación."

        lines = [f"📚 FUENTES INDEXADAS EN EL PROYECTO ({len(index)}):"]
        for item in index:
            lines.append(
                f"• [{item['id']}] {item['name']} ({item['type'].upper()})\n"
                f"  Origen: {item['location']}\n"
                f"  Volumen: {item['word_count']} palabras | Indexado: {item['added_at']}"
            )
        return "\n\n".join(lines)

    def search_sources(self, query: str, max_results: int = 4) -> str:
        """
        Busca fragmentos relevantes dentro de todas las fuentes indexadas
        que coincidan con los términos de la consulta.
        """
        index = self._load_index()
        if not index:
            return "No hay fuentes indexadas disponibles para realizar la búsqueda."

        terms = [t.lower() for t in query.split() if len(t) > 2]
        if not terms:
            terms = [query.lower()]

        results = []

        for item in index:
            content_path = os.path.join(self.content_dir, item["content_file"])
            if not os.path.exists(content_path):
                continue

            try:
                with open(content_path, "r", encoding="utf-8", errors="ignore") as f:
                    lines = f.readlines()

                matched_chunks = []
                for idx, line in enumerate(lines):
                    line_lower = line.lower()
                    score = sum(1 for t in terms if t in line_lower)
                    if score > 0:
                        start_idx = max(0, idx - 1)
                        end_idx = min(len(lines), idx + 3)
                        snippet = "".join(lines[start_idx:end_idx]).strip()
                        matched_chunks.append((score, snippet))

                matched_chunks.sort(key=lambda x: x[0], reverse=True)
                if matched_chunks:
                    top_snippets = [chunk[1] for chunk in matched_chunks[:2]]
                    results.append({
                        "source_name": item["name"],
                        "type": item["type"],
                        "location": item["location"],
                        "snippets": top_snippets
                    })
            except Exception:
                continue

        if not results:
            return f"No se encontraron referencias específicas para '{query}' en las fuentes indexadas."

        output = [f"🔍 REFERENCIAS ENCONTRADAS EN FUENTES INDEXADAS PARA '{query}':\n"]
        for res in results[:max_results]:
            output.append(f"📌 Fuente: {res['source_name']} ({res['type'].upper()} - {res['location']})")
            for snip in res["snippets"]:
                output.append(f"```\n{snip[:800]}\n```")
            output.append("---")

        return "\n".join(output)

    def remove_source(self, identifier: str) -> str:
        """Elimina una fuente del índice por su ID o nombre."""
        index = self._load_index()
        if not index:
            return "El índice de fuentes está vacío."

        target = None
        for item in index:
            if item["id"] == identifier or item["name"].lower() == identifier.lower():
                target = item
                break

        if not target:
            return f"Error: No se encontró la fuente '{identifier}' en el índice."

        content_path = os.path.join(self.content_dir, target["content_file"])
        if os.path.exists(content_path):
            try:
                os.remove(content_path)
            except Exception:
                pass

        new_index = [item for item in index if item["id"] != target["id"]]
        self._save_index(new_index)

        return f"Éxito: Fuente '{target['name']}' ({target['id']}) eliminada del índice."
