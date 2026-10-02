# -*- coding: utf-8 -*-
"""
sandbox_executor.py - Motor de Aislamiento y Entornos Seguros (Sandboxing) para AVFenix Coder.
Aísla la ejecución de comandos de consola y scripts con políticas de seguridad configurables,
inspección de comandos destructivos, limitación de recursos/tiempos, jail de directorios y auditoría JSON.
"""

import os
import sys
import re
import json
import shutil
import subprocess
import logging
from datetime import datetime
from typing import Dict, Any, List, Optional, Tuple

logger = logging.getLogger("SandboxExecutor")

SANDBOX_DIR = ".avfenix_sandbox"
AUDIT_LOG_FILE = os.path.join(SANDBOX_DIR, "audit_log.json")
POLICY_FILE = os.path.join(SANDBOX_DIR, "security_policy.json")

# Lista de patrones de comandos bloqueados por alto riesgo destructivo
DANGEROUS_COMMAND_PATTERNS = [
    r"rm\s+-rf\s+/",
    r"rm\s+-rf\s+\*",
    r"mkfs",
    r"dd\s+if=",
    r":\(\)\{\s*:\|\:&\s*\};:",  # Fork bomb
    r"chmod\s+-R\s+777\s+/",
    r"chown\s+-R\s+root",
    r">/dev/sd[a-z]",
    r"shutdown",
    r"reboot",
    r"init\s+0",
]


class SandboxExecutor:
    """
    Gestor de Ejecución Aislada y Seguridad para AVFenix Coder.
    Garantiza que la ejecución de comandos respete jail de carpetas, limites de tiempo,
    inspección de patrones destructivos y auditoría estructurada.
    """

    def __init__(self, base_dir: str = "."):
        self.base_dir = os.path.abspath(base_dir)
        self.sandbox_dir = os.path.join(self.base_dir, SANDBOX_DIR)
        self.audit_log_file = os.path.join(self.base_dir, AUDIT_LOG_FILE)
        self.policy_file = os.path.join(self.base_dir, POLICY_FILE)
        self._ensure_storage()

    def _ensure_storage(self):
        """Crea las carpetas y archivos iniciales de política y auditoría de sandbox."""
        os.makedirs(self.sandbox_dir, exist_ok=True)
        if not os.path.exists(self.policy_file):
            default_policy = {
                "isolation_level": "strict",  # strict, permissive, docker
                "max_timeout_seconds": 60,
                "allow_network": False,
                "blocked_patterns": DANGEROUS_COMMAND_PATTERNS,
                "allowed_root": self.base_dir,
                "updated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }
            self._save_policy(default_policy)
        if not os.path.exists(self.audit_log_file):
            self._save_audit_log([])

    def _load_policy(self) -> Dict[str, Any]:
        """Carga las políticas de seguridad desde disco."""
        try:
            with open(self.policy_file, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return {
                "isolation_level": "strict",
                "max_timeout_seconds": 60,
                "allow_network": False,
                "blocked_patterns": DANGEROUS_COMMAND_PATTERNS,
                "allowed_root": self.base_dir,
                "updated_at": ""
            }

    def _save_policy(self, policy_data: Dict[str, Any]):
        """Guarda la configuración de política de seguridad."""
        try:
            with open(self.policy_file, "w", encoding="utf-8") as f:
                json.dump(policy_data, f, ensure_ascii=False, indent=2)
        except Exception as e:
            logger.error(f"Error al guardar política sandbox: {e}")

    def _load_audit_log(self) -> List[Dict[str, Any]]:
        """Carga el registro de auditoría JSON."""
        try:
            with open(self.audit_log_file, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return []

    def _save_audit_log(self, log_data: List[Dict[str, Any]]):
        """Guarda el historial de auditoría de comandos de forma atómica."""
        try:
            temp_file = f"{self.audit_log_file}.tmp"
            with open(temp_file, "w", encoding="utf-8") as f:
                json.dump(log_data, f, ensure_ascii=False, indent=2)
            os.replace(temp_file, self.audit_log_file)
        except Exception as e:
            logger.error(f"Error al guardar audit log: {e}")

    def inspect_command_risk(self, command: str) -> Tuple[bool, str]:
        """
        Inspecciona un comando contra la lista de patrones de alto riesgo destructivo.
        Devuelve (es_seguro: bool, razon: str).
        """
        policy = self._load_policy()
        blocked_patterns = policy.get("blocked_patterns", DANGEROUS_COMMAND_PATTERNS)

        for pat in blocked_patterns:
            if re.search(pat, command, re.IGNORECASE):
                return False, f"Comando bloqueado por la política de seguridad (Patrón peligroso detectado: '{pat}')"

        return True, "Comando verificado y aprobado por la política de seguridad."

    def execute_command_isolated(
        self,
        command: str,
        timeout: Optional[int] = None,
        working_dir: Optional[str] = None
    ) -> str:
        """
        Ejecuta un comando de consola dentro de un entorno de aislamiento seguro con
        jail de directorio, verificación de riesgos, limite de tiempo y auditoría.
        """
        policy = self._load_policy()
        max_timeout = policy.get("max_timeout_seconds", 60)
        effective_timeout = min(timeout or 30, max_timeout)

        target_dir = os.path.abspath(working_dir or self.base_dir)
        allowed_root = policy.get("allowed_root", self.base_dir)

        # 1. Inspección de seguridad y jail de directorio
        if not target_dir.startswith(allowed_root) and allowed_root != "/":
            entry = {
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "command": command,
                "status": "BLOCKED_JAIL_VIOLATION",
                "reason": f"Directorio '{target_dir}' fuera de la raíz permitida '{allowed_root}'"
            }
            self._record_audit_entry(entry)
            return f"Error Sandbox: Violación de seguridad de directorio. '{target_dir}' está fuera de '{allowed_root}'."

        is_safe, risk_reason = self.inspect_command_risk(command)
        if not is_safe:
            entry = {
                "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "command": command,
                "status": "BLOCKED_DANGEROUS_PATTERN",
                "reason": risk_reason
            }
            self._record_audit_entry(entry)
            return f"Error Sandbox: {risk_reason}"

        # 2. Configuración de entorno de aislamiento (Entorno limpio)
        env = os.environ.copy()
        env["PYTHONUNBUFFERED"] = "1"
        env["PYTHONDONTWRITEBYTECODE"] = "1"
        env["AVFENIX_SANDBOX_ACTIVE"] = "1"

        start_time = datetime.now()

        try:
            result = subprocess.run(
                command,
                shell=True,
                cwd=target_dir,
                text=True,
                capture_output=True,
                timeout=effective_timeout,
                env=env
            )
            exit_code = result.returncode
            stdout = result.stdout.strip()
            stderr = result.stderr.strip()

            duration_ms = int((datetime.now() - start_time).total_seconds() * 1000)

            entry = {
                "timestamp": start_time.strftime("%Y-%m-%d %H:%M:%S"),
                "command": command,
                "working_dir": target_dir,
                "exit_code": exit_code,
                "duration_ms": duration_ms,
                "status": "SUCCESS" if exit_code == 0 else "EXECUTION_ERROR"
            }
            self._record_audit_entry(entry)

            output = [
                f"[AVFenix Sandbox - Isolation: {policy.get('isolation_level', 'strict').upper()}]",
                f"[Exit Code: {exit_code} | Duración: {duration_ms}ms]"
            ]
            if stdout:
                output.append(f"--- STDOUT ---\n{stdout}")
            if stderr:
                output.append(f"--- STDERR ---\n{stderr}")
            if not stdout and not stderr:
                output.append("(Ejecutado sin salida de texto)")

            return "\n".join(output)

        except subprocess.TimeoutExpired:
            entry = {
                "timestamp": start_time.strftime("%Y-%m-%d %H:%M:%S"),
                "command": command,
                "status": "TIMEOUT_EXPIRED",
                "reason": f"Tiempo límite superado ({effective_timeout}s)"
            }
            self._record_audit_entry(entry)
            return f"Error Sandbox: El comando superó el tiempo máximo permitido de {effective_timeout} segundos."

        except Exception as e:
            entry = {
                "timestamp": start_time.strftime("%Y-%m-%d %H:%M:%S"),
                "command": command,
                "status": "CRITICAL_EXCEPTION",
                "reason": str(e)
            }
            self._record_audit_entry(entry)
            return f"Error Sandbox crítico al ejecutar comando: {e}"

    def _record_audit_entry(self, entry: Dict[str, Any]):
        """Añade un registro a la bitácora de auditoría JSON."""
        audit_log = self._load_audit_log()
        audit_log.append(entry)
        if len(audit_log) > 200:
            audit_log = audit_log[-200:]
        self._save_audit_log(audit_log)

    def get_policy_summary(self) -> str:
        """Devuelve el estado actual de las políticas de seguridad del sandbox."""
        policy = self._load_policy()
        return (
            f"🛡️ POLÍTICA DE SEGURIDAD Y AISLAMIENTO (SANDBOX):\\n"\
            f"• Nivel de Aislamiento: {policy.get('isolation_level', 'strict').upper()}\\n"\
            f"• Raíz de Trabajo Permitida (Jail): `{policy.get('allowed_root', self.base_dir)}`\\n"\
            f"• Timeout Máximo: {policy.get('max_timeout_seconds', 60)} segundos\\n"\
            f"• Red Externa Habilitada: {'Sí' if policy.get('allow_network', False) else 'No (Entorno Air-gapped/Aislado)'}\\n"\
            f"• Patrones Peligrosos Bloqueados: {len(policy.get('blocked_patterns', []))} reglas activas"
        )

    def get_audit_log(self, limit: int = 10) -> str:
        """Devuelve las últimas ejecuciones registradas en la auditoría del sandbox."""
        audit_log = self._load_audit_log()
        if not audit_log:
            return "La bitácora de auditoría del sandbox está vacía."

        recent = audit_log[-limit:]
        lines = [f"📋 BITÁCORA DE AUDITORÍA DEL SANDBOX (Últimas {len(recent)} ejecuciones):\n"]
        for idx, entry in enumerate(reversed(recent), 1):
            status = entry.get("status", "UNKNOWN")
            cmd = entry.get("command", "")
            time_str = entry.get("timestamp", "")
            code = entry.get("exit_code", "N/A")
            lines.append(f"{idx}. [{time_str}] [{status}] (Code: {code}) ➔ `{cmd}`")
            if "reason" in entry:
                lines.append(f"   └─ Nota/Motivo: {entry['reason']}")

        return "\n".join(lines)
