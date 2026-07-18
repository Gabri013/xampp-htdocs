#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ANÁLISE COMPLETA JOTEC - 2137 Códigos

Script para analisar e classificar todos os códigos da JOTEC
Como: INSUMO ou PRODUTO
"""

import json
import os
from datetime import datetime
from collections import defaultdict

# ==================== CONFIGURACAO ====================

arquivo_json = os.path.join(os.path.dirname(__file__), 'codigos_jotec_reais.json')

if not os.path.exists(arquivo_json):
    print("ERRO: Arquivo nao encontrado: {}".format(arquivo_json))
    exit(1)

# Ler JSON
with open(arquivo_json, 'r', encoding='utf-8') as f:
    json_data = json.load(f)

print("=" * 60)
print("ANÁLISE COMPLETA JOTEC - CLASSIFICAÇÃO 2137 CÓDIGOS")
print("=" * 60 + "\n")

# ==================== INFORMAÇÕES GERAIS ====================

print("\nINFORMAÇÕES GERAIS")
print("=" * 60)
print("Arquivo: {}".format(json_data['arquivo']))
print("Total de Códigos: {}".format(json_data['total_codigos']))
print("Total de Abas: {}".format(len(json_data['abas_processadas'])))
print("Abas Processadas: {}".format(', '.join(json_data['abas_processadas'])))

# ==================== ANÁLISE DE CÓDIGOS ====================

# Converter códigos para inteiros
codigos_processados = sorted([int(float(c)) for c in json_data['codigos']])

# Definir ranges com base em padrões típicos JOTEC
ranges_config = [
    {'min': 1000000, 'max': 1000340, 'aba': 'MATERIAIS', 'tipo': 'INSUMO', 'desc': 'Matéria prima, insumos de produção'},
    {'min': 1001501, 'max': 1001504, 'aba': 'GERAL', 'tipo': 'INSUMO', 'desc': 'Materiais gerais'},
    {'min': 1003001, 'max': 1003009, 'aba': 'GERAL', 'tipo': 'INSUMO', 'desc': 'Materiais gerais'},
    {'min': 1004501, 'max': 1004507, 'aba': 'GERAL', 'tipo': 'INSUMO', 'desc': 'Materiais gerais'},
    {'min': 1006001, 'max': 1006498, 'aba': 'INSUMOS DIRETOS', 'tipo': 'INSUMO', 'desc': 'Materiais que entram direto na produção'},
    {'min': 1500000, 'max': 1500157, 'aba': 'REVENDA', 'tipo': 'PRODUTO', 'desc': 'Produtos de revenda, sem produção'},
    {'min': 3000000, 'max': 3000147, 'aba': 'INSUMOS INDIRETOS', 'tipo': 'INSUMO', 'desc': 'Materiais de apoio/consumo/auxiliar'},
    {'min': 3500001, 'max': 3500498, 'aba': 'ATIVO', 'tipo': 'PRODUTO', 'desc': 'Ativos fixos, equipamentos para uso'},
    {'min': 4003001, 'max': 4003498, 'aba': 'MATERIAL DE CONSUMO', 'tipo': 'INSUMO', 'desc': 'Materiais consumíveis'},
]

# Mapear cada código
mapeamento = {}
contagem_abas = defaultdict(int)
contagem_tipos = {'INSUMO': 0, 'PRODUTO': 0, 'DESCONHECIDO': 0}

for codigo in codigos_processados:
    encontrado = False
    for range_info in ranges_config:
        if range_info['min'] <= codigo <= range_info['max']:
            mapeamento[codigo] = {
                'codigo': codigo,
                'aba': range_info['aba'],
                'tipo': range_info['tipo'],
                'descricao': range_info['desc']
            }
            contagem_abas[range_info['aba']] += 1
            contagem_tipos[range_info['tipo']] += 1
            encontrado = True
            break

    if not encontrado:
        mapeamento[codigo] = {
            'codigo': codigo,
            'aba': 'OUTROS',
            'tipo': 'DESCONHECIDO',
            'descricao': 'Código não classificado'
        }
        contagem_abas['OUTROS'] += 1
        contagem_tipos['DESCONHECIDO'] += 1

# ==================== EXIBIR RESULTADOS ====================

print("\n\nDISTRIBUIÇÃO POR ABA")
print("=" * 60 + "\n")

for aba in sorted(contagem_abas.keys()):
    quantidade = contagem_abas[aba]
    # Encontrar tipo
    tipo_info = None
    for range_info in ranges_config:
        if range_info['aba'] == aba:
            tipo_info = "({})".format(range_info['tipo'])
            break
    if not tipo_info:
        tipo_info = "(DESCONHECIDO)"

    print("{}: {} códigos {}".format(aba, quantidade, tipo_info))

print("\n\nRESUMO POR TIPO")
print("=" * 60)
print("INSUMO: {} códigos".format(contagem_tipos['INSUMO']))
print("PRODUTO: {} códigos".format(contagem_tipos['PRODUTO']))
print("DESCONHECIDO: {} códigos".format(contagem_tipos['DESCONHECIDO']))
print("-" * 60)
total = sum(contagem_tipos.values())
print("TOTAL: {} códigos".format(total))

# ==================== SALVAR RESULTADO ====================

resultado = {
    'data': datetime.now().isoformat(),
    'total_codigos': len(codigos_processados),
    'codigo_minimo': min(codigos_processados),
    'codigo_maximo': max(codigos_processados),
    'abas_encontradas': list(contagem_abas.keys()),
    'contagem_por_aba': dict(contagem_abas),
    'contagem_por_tipo': contagem_tipos,
}

# Salvar JSON
json_output = json.dumps(resultado, indent=2, ensure_ascii=False)
json_path = os.path.join(os.path.dirname(__file__), 'analise_jotec_2137_codigos.json')
with open(json_path, 'w', encoding='utf-8') as f:
    f.write(json_output)

print("\nOK - Análise salva em: analise_jotec_2137_codigos.json")

# ==================== GERAR RELATÓRIO ====================

print("\nRELATÓRIO FINAL")
print("=" * 60)
print("Total de códigos analisados: {}".format(len(codigos_processados)))
print("Código mínimo: {}".format(min(codigos_processados)))
print("Código máximo: {}".format(max(codigos_processados)))
print("Range total: {}".format(max(codigos_processados) - min(codigos_processados) + 1))

# ==================== SALVAR MAPEAMENTO COMPLETO ====================

# Agrupar mapeamento por aba para relatório legível
mapeamento_por_aba = defaultdict(list)
for codigo, info in mapeamento.items():
    mapeamento_por_aba[info['aba']].append(info)

# Gerar arquivo com mapeamento formatado
relatorio = []
relatorio.append("=" * 60)
relatorio.append("MAPEAMENTO COMPLETO JOTEC - 2137 CÓDIGOS")
relatorio.append("=" * 60 + "\n")
relatorio.append("Data: {}".format(datetime.now().strftime('%Y-%m-%d %H:%M:%S')))

for aba in sorted(mapeamento_por_aba.keys()):
    codigos = sorted([c['codigo'] for c in mapeamento_por_aba[aba]])
    tipo = mapeamento_por_aba[aba][0]['tipo'] if mapeamento_por_aba[aba] else 'DESCONHECIDO'
    descricao = mapeamento_por_aba[aba][0]['descricao'] if mapeamento_por_aba[aba] else ''

    relatorio.append("\n\nABA: {}".format(aba))
    relatorio.append("   Tipo: {}".format(tipo))
    relatorio.append("   Descrição: {}".format(descricao))
    relatorio.append("   Quantidade: {} códigos".format(len(codigos)))
    relatorio.append("   Range: {} - {}".format(min(codigos), max(codigos)))
    relatorio.append("   Códigos: {}".format(', '.join(str(c) for c in codigos[:10])))
    if len(codigos) > 10:
        relatorio.append("            ... (+{} mais)".format(len(codigos) - 10))

relatorio_text = '\n'.join(relatorio)

# Salvar relatório
relatorio_path = os.path.join(os.path.dirname(__file__), 'relatorio_jotec_2137_codigos.txt')
with open(relatorio_path, 'w', encoding='utf-8') as f:
    f.write(relatorio_text)

print("OK - Relatório salvo em: relatorio_jotec_2137_codigos.txt\n")

# Exibir resumo
print("\n" + "=" * 55)
print("ANÁLISE COMPLETA COM SUCESSO")
print("=" * 55)
print("\nArquivos gerados:")
print("  1. analise_jotec_2137_codigos.json - Dados estruturados")
print("  2. relatorio_jotec_2137_codigos.txt - Relatório legível")

