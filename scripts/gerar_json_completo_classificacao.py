#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gerar JSON completo com todos os 2137 codigos JOTEC classificados
"""

import json
import os
from datetime import datetime

# Ler JSON original com os codigos
arquivo_json = os.path.join(os.path.dirname(__file__), 'codigos_jotec_reais.json')
with open(arquivo_json, 'r', encoding='utf-8') as f:
    json_data = json.load(f)

# Converter codigos para inteiros e ordenar
codigos = sorted([int(float(c)) for c in json_data['codigos']])

# Definir ranges com classificacao
ranges_jotec = [
    {'min': 992, 'max': 999, 'tipo': 'LEGADO', 'aba': 'PRODUTOS ACABADOS', 'categoria': 'Legado'},
    {'min': 1000000, 'max': 1000058, 'tipo': 'INSUMO', 'aba': 'MATERIAIS', 'categoria': 'Materia Prima'},
    {'min': 1001501, 'max': 1001504, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1003001, 'max': 1003009, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1004501, 'max': 1004507, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1004508, 'max': 1004508, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1006001, 'max': 1006489, 'tipo': 'INSUMO', 'aba': 'INSUMOS DIRETOS', 'categoria': 'Componente'},
    {'min': 1007503, 'max': 1007530, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1010501, 'max': 1010529, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1012001, 'max': 1012012, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1013501, 'max': 1013508, 'tipo': 'INSUMO', 'aba': 'GERAL', 'categoria': 'Geral'},
    {'min': 1500000, 'max': 1500155, 'tipo': 'PRODUTO', 'aba': 'REVENDA', 'categoria': 'Revenda'},
    {'min': 3000000, 'max': 3000149, 'tipo': 'INSUMO', 'aba': 'INSUMOS INDIRETOS', 'categoria': 'Consumo'},
    {'min': 3001501, 'max': 3001512, 'tipo': 'INSUMO', 'aba': 'INSUMOS INDIRETOS', 'categoria': 'Consumo'},
    {'min': 3003001, 'max': 3003008, 'tipo': 'INSUMO', 'aba': 'INSUMOS INDIRETOS', 'categoria': 'Consumo'},
    {'min': 3004501, 'max': 3004517, 'tipo': 'INSUMO', 'aba': 'INSUMOS INDIRETOS', 'categoria': 'Consumo'},
    {'min': 3500001, 'max': 3500498, 'tipo': 'PRODUTO', 'aba': 'ATIVO', 'categoria': 'Ativo Fixo'},
    {'min': 4000000, 'max': 4000003, 'tipo': 'INSUMO', 'aba': 'MATERIAL DE CONSUMO', 'categoria': 'Consumivel'},
    {'min': 4001501, 'max': 4001552, 'tipo': 'INSUMO', 'aba': 'MATERIAL DE CONSUMO', 'categoria': 'Consumivel'},
    {'min': 4003001, 'max': 4003498, 'tipo': 'INSUMO', 'aba': 'MATERIAL DE CONSUMO', 'categoria': 'Consumivel'},
]

# Classificar cada codigo
def classificar_codigo(codigo, ranges):
    for r in ranges:
        if r['min'] <= codigo <= r['max']:
            return {
                'tipo': r['tipo'],
                'aba': r['aba'],
                'categoria': r['categoria'],
                'range_inicio': r['min'],
                'range_fim': r['max']
            }
    return {
        'tipo': 'DESCONHECIDO',
        'aba': 'OUTROS',
        'categoria': 'Nao classificado',
        'range_inicio': codigo,
        'range_fim': codigo
    }

print("Processando {} codigos...".format(len(codigos)))

# Gerar lista completa
codigos_classificados = []
contagem_tipos = {'INSUMO': 0, 'PRODUTO': 0, 'LEGADO': 0, 'DESCONHECIDO': 0}
contagem_abas = {}

for codigo in codigos:
    class_info = classificar_codigo(codigo, ranges_jotec)

    codigos_classificados.append({
        'codigo': codigo,
        'tipo': class_info['tipo'],
        'aba': class_info['aba'],
        'categoria': class_info['categoria'],
        'range_inicio': class_info['range_inicio'],
        'range_fim': class_info['range_fim'],
        'status': 'ativo'
    })

    contagem_tipos[class_info['tipo']] += 1

    aba = class_info['aba']
    if aba not in contagem_abas:
        contagem_abas[aba] = 0
    contagem_abas[aba] += 1

# Gerar resultado
resultado = {
    'data_geracao': datetime.now().isoformat(),
    'fonte': 'codigos_jotec_reais.json',
    'total_codigos': len(codigos_classificados),
    'contagem_por_tipo': contagem_tipos,
    'contagem_por_aba': contagem_abas,
    'codigos_classificados': codigos_classificados
}

# Salvar
json_output = json.dumps(resultado, indent=2, ensure_ascii=False)
output_path = os.path.join(os.path.dirname(__file__), 'jotec_classificacao_completa.json')

with open(output_path, 'w', encoding='utf-8') as f:
    f.write(json_output)

print("\nOK - Arquivo salvo: jotec_classificacao_completa.json")
print("\nResumo:")
print("Total: {}".format(len(codigos_classificados)))
for tipo, qtd in sorted(contagem_tipos.items()):
    print("  {}: {}".format(tipo, qtd))

print("\nPor aba:")
for aba, qtd in sorted(contagem_abas.items()):
    print("  {}: {}".format(aba, qtd))

