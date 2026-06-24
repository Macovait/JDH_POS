#!/usr/bin/env python3
import subprocess, sys, os, json

def run_mysql(sql):
    r = subprocess.run(
        ['mysql', '-u', 'root', '-e', sql],
        capture_output=True, text=True
    )
    return r.stdout, r.stderr

# Check voided column
out, err = run_mysql('USE jdh_pos; SHOW COLUMNS FROM sales LIKE "voided";')
print('voided column:', 'EXISTS' if 'voided' in out else 'MISSING')

# Check tables
for table in ['register_sessions', 'shifts']:
    out, err = run_mysql(f'USE jdh_pos; SHOW TABLES LIKE "{table}";')
    print(f'{table}:', 'EXISTS' if table in out else 'MISSING')
