#!/usr/bin/env python3
"""PLC simulasi stasiun pompa air (Modbus TCP, port 502). LINGKUNGAN LATIHAN.

Peta register:
  coil 0          : pompa utama (1 = jalan)
  coil 1          : katup darurat (1 = terbuka)
  holding reg 0   : setpoint tekanan (kPa)
  holding reg 1   : batas level tangki (%)
"""
import logging

from pymodbus.datastore import ModbusSequentialDataBlock, ModbusServerContext, ModbusSlaveContext
from pymodbus.server import StartTcpServer

logging.basicConfig(level=logging.INFO, format="%(asctime)s plc-sim %(message)s")

store = ModbusSlaveContext(
    co=ModbusSequentialDataBlock(0, [1, 0] + [0] * 14),
    di=ModbusSequentialDataBlock(0, [0] * 16),
    hr=ModbusSequentialDataBlock(0, [350, 80] + [0] * 30),
    ir=ModbusSequentialDataBlock(0, [0] * 16),
)
context = ModbusServerContext(slaves=store, single=True)

if __name__ == "__main__":
    logging.info("PLC simulasi stasiun pompa siap di 0.0.0.0:502")
    StartTcpServer(context=context, address=("0.0.0.0", 502))
