# -*- coding: utf-8 -*-
"""
user_style_memory.py - Perfil de Estilo y Memoria Adaptativa para AVFenix Coder.
Analiza e infiere automáticamente los hábitos de programación, convenciones de nombrado,
anotaciones de tipo, docstrings e idiomas preferidos del usuario, e inyecta las pautas
acumuladas en el System Prompt.
"""

import os
import re
import json
import logging
from typing import Dict, Any, List, Optional

logger = logging.getLogger("UserStyleMemory")

DEFAULT_PROFILE_FILE = ".avfenix_memory/user_coding_profile.json"


class UserStyleMemory:
    """
    Administra el perfil de estilo de codificación del usuario.
    Infiere reglas observando el código creado o modificado y almacena
    instrucciones explícitas expresadas por el usuario.
    """

    def __init__(self, base_dir: str = ".", profile_file: str = DEFAULT_PROFILE_FILE):
        self.base_dir = os.path.abspath(base_dir)
        self.profile_path = os.path.join(self.base_dir, profile_file)
        self.profile_data: Dict[str, Any] = {
            "version": "1.0",
            "naming_convention": "snake_case",
            "docstring_style": "Google/Multilínea en Español",
            "type_hints": True,
            "error_handling": "Excepciones específicas y mensajes claros",
            "preferred_language": "Español",
            "explicit_rules": [],
            "observed_patterns": []
        }
        self._ensure_storage()
        self.load_profile()

    def _ensure_storage(self):
        os.makedirs(os.path.dirname(self.profile_path), exist_ok=True)

    def load_profile(self) -> Dict[str, Any]:
        """Carga el perfil de estilo desde el archivo JSON si existe."""
        if not os.path.exists(self.profile_path):
            self.save_profile()
            return self.profile_data

        try:
            with open(self.profile_path, "r", encoding="utf-8") as f:
                data = json.load(f)
                if isinstance(data, dict):
                    self.profile_data.update(data)
            logger.info(f"Perfil de estilo cargado desde '{self.profile_path}'.")
        except Exception as e:
            logger.error(f"Error al leer perfil de estilo: {e}")

        return self.profile_data

    def save_profile(self) -> bool:
        """Guarda el perfil de estilo actualizado en disco."""
        try:
            with open(self.profile_path, "w", encoding="utf-8") as f:
                json.dump(self.profile_data, f, ensure_ascii=False, indent=2)
            return True
        except Exception as e:
            logger.error(f"Error al guardar perfil de estilo: {e}")
            return False

    def learn_preference(self, rule_or_pattern: str, category: str = "explicit") -> str:
        """
        Registra una nueva preferencia o regla de estilo explicada por el usuario.
        """
        rule = rule_or_pattern.strip()
        if not rule:
            return "Error: La regla de estilo no puede estar vacía."

        if category == "explicit":
            rules = self.profile_data.get("explicit_rules", [])
            if rule not in rules:
                rules.append(rule)
                self.profile_data["explicit_rules"] = rules
                self.save_profile()
            return f"Éxito: Pauta de estilo guardada en tu perfil ('{rule}')."
        else:
            patterns = self.profile_data.get("observed_patterns", [])
            if rule not in patterns:
                patterns.append(rule)
                self.profile_data["observed_patterns"] = patterns
                self.save_profile()
            return f"Éxito: Patrón de estilo observado y registrado ('{rule}')."

    def analyze_and_learn_from_code(self, filepath: str, code_content: str):
        """
        Analiza un fragmento de código escrito para inferir convenciones de estilo.
        """
        if not code_content or len(code_content.strip()) < 20:
            return

        # Inferir uso de type hints
        if re.search(r"def\s+\w+\(.*:\s*\w+.*\)\s*->", code_content):
            self.profile_data["type_hints"] = True

        # Inferir docstrings en español
        if '"""' in code_content or "'''" in code_content:
            if re.search(r"[áéíóúÁÉÍÓÚñÑ]", code_content):
                self.profile_data["docstring_style"] = "Docstrings en Español detallados"

        # Inferir snake_case vs camelCase
        if re.search(r"def\s+[a-z]+_[a-z_]+\(", code_content):
            self.profile_data["naming_convention"] = "snake_case"

        self.save_profile()

    def get_prompt_injection(self) -> str:
        """
        Genera el bloque de instrucciones de estilo para inyectar en el System Prompt.
        """
        rules = self.profile_data.get("explicit_rules", [])
        rules_text = "\n".join([f"- {r}" for r in rules]) if rules else "- Mantén código limpio, modular y documentado."

        return (
            "\n--- 🧠 PERFIL Y MEMORIA DE ESTILO DEL USUARIO (IMITAR ESTE ESTILO) ---\n"
            f"- Convención de Nombrado: {self.profile_data.get('naming_convention', 'snake_case')}\n"
            f"- Formato de Docstrings: {self.profile_data.get('docstring_style', 'Google/Español')}\n"
            f"- Anotaciones de Tipo (Type Hints): {'Sí (Estrictas)' if self.profile_data.get('type_hints') else 'Opcional'}\n"
            f"- Idioma de Comentarios: {self.profile_data.get('preferred_language', 'Español')}\n"
            "Reglas y Preferencias Explícitas del Usuario:\n"
            f"{rules_text}\n"
            "INSTRUCCIÓN: Programa e imita siempre las pautas y estilo anteriores para hacer el código cada vez más parecido a sus preferencias.\n"
        )

    def get_profile_summary(self) -> str:
        """Devuelve un resumen textual formateado del perfil de estilo para la TUI."""
        rules = self.profile_data.get("explicit_rules", [])
        rules_str = "\n".join([f"  • {r}" for r in rules]) if rules else "  • Ninguna regla explícita configurada aún."

        return (
            "🧠 PERFIL DE ESTILO Y MEMORIA ADAPTATIVA DEL USUARIO:\n"
            f"• Convención de Nombrado: {self.profile_data.get('naming_convention')}\n"
            f"• Estilo de Docstrings: {self.profile_data.get('docstring_style')}\n"
            f"• Type Hints: {'Activado' if self.profile_data.get('type_hints') else 'Desactivado'}\n"
            f"• Idioma Preferido: {self.profile_data.get('preferred_language')}\n"
            f"Reglas Personalizadas Registradas:\n{rules_str}"
        )
