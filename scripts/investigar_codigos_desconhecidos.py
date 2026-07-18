#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Investigar os 179 códigos desconhecidos do JOTEC
"""

import json
import os
from collections import defaultdict

# Ler JSON
arquivo_json = os.path.join(os.path.dirname(__file__), 'codigos_jotec_reais.json')
with open(arquivo_json, 'r', encoding='utf-8') as f:
    json_data = json.load(f)

# Converter códigos para inteiros
codigos_processados = sorted([int(float(c)) for c in json_data['codigos']])

# Definir ranges conhecidos
ranges_config = [
    {'min': 1000000, 'max': 1000340, 'aba': 'MATERIAIS'},
    {'min': 1001501, 'max': 1001504, 'aba': 'GERAL'},
    {'min': 1003001, 'max': 1003009, 'aba': 'GERAL'},
    {'min': 1004501, 'max': 1004507, 'aba': 'GERAL'},
    {'min': 1006001, 'max': 1006498, 'aba': 'INSUMOS DIRETOS'},
    {'min': 1500000, 'max': 1500157, 'aba': 'REVENDA'},
    {'min': 3000000, 'max': 3000147, 'aba': 'INSUMOS INDIRETOS'},
    {'min': 3500001, 'max': 3500498, 'aba': 'ATIVO'},
    {'min': 4003001, 'max': 4003498, 'aba': 'MATERIAL DE CONSUMO'},
]

# Encontrar códigos desconhecidos
desconhecidos = []
for codigo in codigos_processados:
    encontrado = False
    for r in ranges_config:
        if r['min'] <= codigo <= r['max']:
            encontrado = True
            break
    if not encontrado:
        desconhecidos.append(codigo)

print("=" * 70)
print("INVESTIGAÇÃO DE CÓDIGOS DESCONHECIDOS JOTEC")
print("=" * 70)
print("\nTotal de códigos desconhecidos: {}".format(len(desconhecidos)))
print("Range geral: {} a {}".format(min(desconhecidos), max(desconhecidos)))

# Agrupar em ranges menores para visualizar padrões
print("\n\nANÁLISE POR FAIXA NUMÉRICA")
print("=" * 70 + "\n")

# Usar ranges de 1000 em 1000
ranges_analise = defaultdict(list)
for codigo in desconhecidos:
    faixa = (codigo // 1000) * 1000
    ranges_analise[faixa].append(codigo)

for faixa in sorted(ranges_analise.keys()):
    codigos_faixa = ranges_analise[faixa]
    min_faixa = min(codigos_faixa)
    max_faixa = max(codigos_faixa)
    print("Faixa {}000-{}999: {} códigos (min: {}, max: {})".format(
        faixa // 1000, (faixa // 1000),
        len(codigos_faixa), min_faixa, max_faixa
    ))

# Classificar códigos desconhecidos baseado em padrões
print("\n\nCLASSIFICAÇÃO POR PADRÃO")
print("=" * 70 + "\n")

classifacao_desconhecidos = []
for codigo in desconhecidos:
    # Tentar classificar por padrão numérico
    if codigo < 1000:
        # Códigos muito baixos (992-999) - provavelmente produtos ou legado
        tipo = "PRODUTO (legado/teste)"
        aba = "PRODUTOS ACABADOS"
    elif codigo >= 1000000 and codigo < 1006000:
        # Faixa 100xxxx - materiais/produtos
        if codigo <= 1000340:
            tipo = "INSUMO"
            aba = "MATERIAIS"
        else:
            tipo = "DESCONHECIDO"
            aba = "GAP"
    elif codigo >= 1006490 and codigo < 1500000:
        # Entre insumos diretos e revenda
        tipo = "DESCONHECIDO"
        aba = "GAP"
    elif codigo >= 1500158 and codigo < 3000000:
        # Entre revenda e insumos indiretos
        tipo = "DESCONHECIDO"
        aba = "GAP"
    elif codigo >= 3000148 and codigo < 3500001:
        # Entre insumos indiretos e ativos
        tipo = "DESCONHECIDO"
        aba = "GAP"
    elif codigo >= 3500499 and codigo < 4003001:
        # Entre ativos e material consumo
        tipo = "DESCONHECIDO"
        aba = "GAP"
    elif codigo >= 4003499:
        # Acima de material consumo
        tipo = "DESCONHECIDO"
        aba = "ALÉM DO FINAL"
    else:
        tipo = "DESCONHECIDO"
        aba = "INDEFINIDO"

    classifacao_desconhecidos.append({
        'codigo': codigo,
        'tipo': tipo,
        'aba': aba
    })

# Contar por tipo
contagem_tipos = defaultdict(int)
for item in classifacao_desconhecidos:
    contagem_tipos[item['tipo']] += 1

print("Distribuição de tipos:")
for tipo, quantidade in sorted(contagem_tipos.items(), key=lambda x: -x[1]):
    print("  {}: {} códigos".format(tipo, quantidade))

# Exibir alguns exemplos
print("\n\nEXEMPLOS DE CÓDIGOS DESCONHECIDOS")
print("=" * 70 + "\n")

exemplos = []
for item in classifacao_desconhecidos:
    if item['tipo'] not in [e['tipo'] for e in exemplos]:
        exemplos.append(item)

for exemplo in exemplos:
    print("Código {} - Classificação: {} - ABA: {}".format(
        exemplo['codigo'], exemplo['tipo'], exemplo['aba']
    ))

# Salvar resultado
resultado_desconhecidos = {
    'total': len(desconhecidos),
    'codigos': desconhecidos,
    'range_minimo': min(desconhecidos),
    'range_maximo': max(desconhecidos),
    'classificacoes': classifacao_desconhecidos,
    'contagem_por_tipo': dict(contagem_tipos)
}

json_output = json.dumps(resultado_desconhecidos, indent=2, ensure_ascii=False)
json_path = os.path.join(os.path.dirname(__file__), 'codigos_desconhecidos_analise.json')
with open(json_path, 'w', encoding='utf-8') as f:
    f.write(json_output)

print("\n\nOK - Análise salva em: codigos_desconhecidos_analise.json")

