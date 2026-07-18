#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import sys
import os
import json
sys.stdout.reconfigure(encoding='utf-8')

import xlrd

arquivo = r"C:\Users\gabri\Downloads\CADASTRO PRODUTOS JOTEC - 2019 C.xls"

print("="*70)
print("LER CODIGOS REAIS DO EXCEL JOTEC (XLS)")
print("="*70)
print()

if not os.path.exists(arquivo):
    print(f"[ERROR] Arquivo nao encontrado: {arquivo}\n")
    sys.exit(1)

print(f"Arquivo: {os.path.basename(arquivo)}")
print(f"Tamanho: {os.path.getsize(arquivo) / (1024*1024):.2f} MB\n")

try:
    # Abrir workbook
    wb = xlrd.open_workbook(arquivo)
    print(f"[OK] Arquivo lido com xlrd!")
    print(f"Total de abas: {len(wb.sheet_names())}\n")

    todos_codigos = []
    dados_por_aba = {}

    for aba_idx, aba_nome in enumerate(wb.sheet_names(), 1):
        print(f"[ABA {aba_idx}] {aba_nome}")
        print("-" * 70)

        ws = wb.sheet_by_name(aba_nome)
        print(f"  Linhas: {ws.nrows}, Colunas: {ws.ncols}")

        # Ler headers
        headers = []
        for col in range(min(ws.ncols, 10)):
            val = ws.cell(0, col).value
            if val:
                headers.append((col, str(val).strip()))

        print(f"  Headers encontrados: {len(headers)}")
        if headers:
            print(f"    {', '.join([h[1][:15] for h in headers[:5]])}")

        codigos_aba = []

        # Ler dados
        for row_idx in range(1, min(ws.nrows, 500)):  # Primeiras 500 linhas
            # Procurar codigo na primeira coluna ou coluna com 'cod'
            codigo = None

            # Tentar primeira coluna
            primeira_col = ws.cell(row_idx, 0).value
            if primeira_col:
                codigo = str(primeira_col).strip()

            # Verificar se eh valido (nao eh header)
            if codigo and codigo not in ['Codigo', 'Code', 'COD', '']:
                # Verificar se eh numero ou codigo
                try:
                    # Tentar converter para numero
                    if str(codigo).isdigit() or '.' in str(codigo):
                        codigos_aba.append(codigo)
                        todos_codigos.append({
                            'codigo': str(codigo),
                            'aba': aba_nome,
                            'linha': row_idx
                        })

                        # Amostra
                        if len(codigos_aba) <= 5:
                            print(f"    Linha {row_idx}: {codigo}")
                except:
                    pass

        print(f"  Codigos encontrados: {len(codigos_aba)}\n")
        dados_por_aba[aba_nome] = codigos_aba[:10]  # Primeiros 10

    # Analise
    print("="*70)
    print("ANALISE")
    print("="*70)
    print()

    codigos_unicos = sorted(set([str(c['codigo']) for c in todos_codigos]))
    print(f"Total de codigos unicos: {len(codigos_unicos)}\n")

    print("PRIMEIROS 50 CODIGOS ENCONTRADOS:")
    for i, cod in enumerate(codigos_unicos[:50], 1):
        print(f"  {i:2d}. {cod}")

    if len(codigos_unicos) > 50:
        print(f"  ... + {len(codigos_unicos) - 50} codigos\n")

    # Detectar padrao
    print("\nPADRAO DETECTADO:")
    print("-" * 70)

    # Verificar se sao numeros
    try:
        primeiros_nums = [float(c) for c in codigos_unicos[:5]]
        print(f"Tipo: Numerico sequencial")
        print(f"Exemplo: {codigos_unicos[0]} -> {codigos_unicos[1]} -> {codigos_unicos[2]}")
    except:
        print(f"Tipo: Alfanumerico/Misto")
        print(f"Exemplo: {', '.join(codigos_unicos[:5])}")

    print()

    # Salvar JSON
    output_file = r"C:\xampp\htdocs\scripts\codigos_jotec_reais.json"

    saida = {
        "arquivo": os.path.basename(arquivo),
        "total_codigos": len(codigos_unicos),
        "abas_processadas": list(dados_por_aba.keys()),
        "codigos": codigos_unicos,
        "amostra_primeiros_50": codigos_unicos[:50]
    }

    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(saida, f, ensure_ascii=False, indent=2)

    print(f"[OK] Codigos salvos em: {output_file}\n")

    # Gerar arquivo PHP com os codigos
    php_file = r"C:\xampp\htdocs\scripts\codigos_jotec_reais.php"

    php_content = f"""<?php
/**
 * CODIGOS REAIS DO EXCEL JOTEC
 * Gerado automaticamente em 2026-07-17
 * Total de codigos: {len(codigos_unicos)}
 */

return array(
    'total' => {len(codigos_unicos)},
    'abas' => {json.dumps(list(dados_por_aba.keys()))},
    'codigos' => array(
"""

    for cod in codigos_unicos[:100]:  # Salvar primeiros 100
        php_content += f"        '{cod}',\n"

    php_content += """    )
);
?>"""

    with open(php_file, 'w', encoding='utf-8') as f:
        f.write(php_content)

    print(f"[OK] PHP gerado em: {php_file}\n")

    print("="*70)
    print("LEITURA COMPLETA COM SUCESSO!")
    print("="*70)

except Exception as e:
    print(f"[ERROR] {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)
