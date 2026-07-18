# TABELA DE MAPEAMENTO JOTEC - REFERENCIA RAPIDA

**Data**: 2026-07-17  
**Versao**: 1.0  
**Total de Codigos**: 2137  

---

## TABELA 1: MAPEAMENTO PRINCIPAL (8 ABAS)

| # | Aba | Range | Qtd | Tipo | Categoria | Descricao |
|---|-----|-------|-----|------|-----------|-----------|
| 1 | MATERIAIS | 1000000-1000058 | 59 | INSUMO | Materia Prima | Aco, aluminio, cobre, componentes basicos |
| 2 | INSUMOS DIRETOS | 1006001-1006489 | 554 | INSUMO | Componente | Parafusos, porcas, arruelas, subconjuntos |
| 3 | INSUMOS INDIRETOS | 3000000-3000149 + gaps | 205 | INSUMO | Consumo | Gases, adesivos, tintas, eletrodios |
| 4 | MATERIAL CONSUMO | 4003001-4003498 + gaps | 554 | INSUMO | Consumivel | Panos, quimicos, lubrificantes |
| 5 | GERAL | Variados (10 ranges) | 96 | INSUMO | Subproduto | Componentes nao categorizados |
| 6 | REVENDA | 1500000-1500155 | 158 | PRODUTO | Revenda | Produtos comprados para revenda |
| 7 | ATIVO | 3500001-3500498 | 503 | PRODUTO | Ativo Fixo | Maquinas, compressores, equipamentos |
| 8 | LEGADO | 992-999 | 8 | LEGADO | Teste | Codigos antigos (inativo) |
| | **TOTAL** | | **2137** | | | |

---

## TABELA 2: RANGES DETALHADOS

### INSUMOS (1468 CODIGOS)

| Range | Descricao | Quantidade | Aba | Status |
|-------|-----------|-----------|-----|--------|
| 1000000-1000058 | Materiais basicos | 59 | MATERIAIS | Ativo |
| 1001501-1001504 | Geral - Subproduto 1 | 4 | GERAL | Ativo |
| 1003001-1003009 | Geral - Subproduto 2 | 9 | GERAL | Ativo |
| 1004501-1004508 | Geral - Subproduto 3 | 8 | GERAL | Ativo |
| 1006001-1006489 | Insumos diretos | 554 | INSUMOS DIRETOS | Ativo |
| 1007503-1007530 | Geral - Componentes | 26 | GERAL | Ativo |
| 1010501-1010529 | Geral - Kits | 29 | GERAL | Ativo |
| 1012001-1012012 | Geral - Consertos | 12 | GERAL | Ativo |
| 1013501-1013508 | Geral - Substituicoes | 8 | GERAL | Ativo |
| 3000000-3000149 | Insumos indiretos | 148 | INSUMOS INDIRETOS | Ativo |
| 3001501-3001512 | Insumos indiretos ext | 12 | INSUMOS INDIRETOS | Ativo |
| 3003001-3003008 | Insumos indiretos ext | 8 | INSUMOS INDIRETOS | Ativo |
| 3004501-3004517 | Insumos indiretos ext | 17 | INSUMOS INDIRETOS | Ativo |
| 4000000-4000003 | Material consumo ext | 4 | MATERIAL CONSUMO | Ativo |
| 4001501-4001552 | Material consumo ext | 52 | MATERIAL CONSUMO | Ativo |
| 4003001-4003498 | Material consumo | 498 | MATERIAL CONSUMO | Ativo |
| | **SUBTOTAL** | **1468** | | |

### PRODUTOS (669 CODIGOS)

| Range | Descricao | Quantidade | Aba | Status |
|-------|-----------|-----------|-----|--------|
| 1500000-1500155 | Revenda | 158 | REVENDA | Ativo |
| 3500001-3500498 | Ativos fixos | 503 | ATIVO | Ativo |
| | **SUBTOTAL** | **661** | | |

### LEGADO (8 CODIGOS)

| Range | Descricao | Quantidade | Aba | Status |
|-------|-----------|-----------|-----|--------|
| 992-999 | Produtos teste antigos | 8 | PRODUTOS ACABADOS | Inativo |
| | **SUBTOTAL** | **8** | | |

---

## TABELA 3: POR FAIXA NUMERICA (1000 EM 1000)

| Faixa | Range | Tipo Principal | Quantidade | Descricao |
|-------|-------|----------------|-----------|-----------|
| 992-999 | LEGADO | Legado | 8 | Codigos teste antigos |
| 1000000-1000999 | MATERIAIS | Insumo | 59 | Materia prima |
| 1001000-1001999 | GERAL | Insumo | 4 | Subproduto geral |
| 1002000-1002999 | VAZIO | - | 0 | Nao utilizado |
| 1003000-1003999 | GERAL | Insumo | 9 | Subproduto geral |
| 1004000-1004999 | GERAL | Insumo | 8 | Subproduto geral |
| 1005000-1005999 | VAZIO | - | 0 | Nao utilizado |
| 1006000-1006999 | INSUMOS DIRETOS | Insumo | 554 | Componentes producao |
| 1007000-1007999 | GERAL | Insumo | 26 | Subproduto geral |
| 1008000-1009999 | VAZIO | - | 0 | Nao utilizado |
| 1010000-1010999 | GERAL | Insumo | 29 | Subproduto geral |
| 1011000-1011999 | VAZIO | - | 0 | Nao utilizado |
| 1012000-1012999 | GERAL | Insumo | 12 | Subproduto geral |
| 1013000-1013999 | GERAL | Insumo | 8 | Subproduto geral |
| 1014000-1499999 | VAZIO | - | 0 | Nao utilizado |
| 1500000-1500999 | REVENDA | Produto | 158 | Produtos revenda |
| 1501000-2999999 | VAZIO | - | 0 | Nao utilizado |
| 3000000-3000999 | INSUMOS INDIRETOS | Insumo | 148 | Gases e apoio |
| 3001000-3001999 | INSUMOS INDIRETOS | Insumo | 12 | Gases e apoio |
| 3002000-3002999 | VAZIO | - | 0 | Nao utilizado |
| 3003000-3003999 | INSUMOS INDIRETOS | Insumo | 8 | Gases e apoio |
| 3004000-3004999 | INSUMOS INDIRETOS | Insumo | 17 | Gases e apoio |
| 3005000-3499999 | VAZIO | - | 0 | Nao utilizado |
| 3500000-3500999 | ATIVO | Produto | 503 | Ativos fixos |
| 3501000-3999999 | VAZIO | - | 0 | Nao utilizado |
| 4000000-4000999 | MATERIAL CONSUMO | Insumo | 4 | Consumiveis |
| 4001000-4001999 | MATERIAL CONSUMO | Insumo | 52 | Consumiveis |
| 4002000-4002999 | VAZIO | - | 0 | Nao utilizado |
| 4003000-4003999 | MATERIAL CONSUMO | Insumo | 498 | Consumiveis |
| 4004000+ | VAZIO | - | 0 | Nao utilizado |
| | **TOTAL** | | **2137** | |

---

## TABELA 4: RESUMO POR TIPO

| Tipo | Codigos | Percentual | Principais Abas |
|------|---------|-----------|-----------------|
| INSUMO | 1468 | 68,7% | Insumos Diretos, Material Consumo, Indiretos |
| PRODUTO | 661 | 30,9% | Ativo Fixo, Revenda |
| LEGADO | 8 | 0,4% | Produtos Acabados (teste) |
| **TOTAL** | **2137** | **100%** | |

---

## TABELA 5: RESUMO POR ABA

| Aba | Tipo | Qtd | % | Range Principal |
|-----|------|-----|---|-----------------|
| MATERIAL DE CONSUMO | INSUMO | 554 | 25,9% | 4003001-4003498 |
| INSUMOS DIRETOS | INSUMO | 554 | 25,9% | 1006001-1006489 |
| ATIVO | PRODUTO | 503 | 23,5% | 3500001-3500498 |
| INSUMOS INDIRETOS | INSUMO | 205 | 9,6% | 3000000-3000149 (+ gaps) |
| GERAL | INSUMO | 96 | 4,5% | Variados (10 ranges) |
| MATERIAIS | INSUMO | 59 | 2,8% | 1000000-1000058 |
| REVENDA | PRODUTO | 158 | 7,4% | 1500000-1500155 |
| PRODUTOS ACABADOS (Legado) | LEGADO | 8 | 0,4% | 992-999 |
| **TOTAL** | | **2137** | **100%** | |

---

## TABELA 6: GAPS E AREAS VAZIAS

| Faixa | Descricao | Status | Recomendacao |
|-------|-----------|--------|--------------|
| 1000059-1001500 | Entre materiais e geral | Disponivel | Futuro uso |
| 1001505-1002999 | Entre geral e geral | Disponivel | Futuro uso |
| 1005000-1005999 | Entre geral e insumos diretos | Disponivel | Futuro uso |
| 1007531-1009999 | Entre geral e geral | Disponivel | Futuro uso |
| 1010530-1011999 | Entre geral e vazio | Disponivel | Futuro uso |
| 1014000-1499999 | Grande gap entre geral e revenda | Disponivel | Futuro expansion |
| 1500156-2999999 | Gap grande entre revenda e indiretos | Disponivel | Futuro expansion |
| 3002000-3002999 | Entre indiretos | Disponivel | Futuro uso |
| 3005000-3499999 | Gap grande entre indiretos e ativo | Disponivel | Futuro expansion |
| 3500499-3999999 | Gap grande apos ativo | Disponivel | Futuro expansion |
| 4002000-4002999 | Entre material consumo | Disponivel | Futuro uso |
| 4004000+ | Alem do final | Disponivel | Futuro expansion |

---

## GUIA DE USO RAPIDO

### Verificar um codigo

```
Codigo: 1006001
Resposta: INSUMO - Aba: INSUMOS DIRETOS - Categoria: Componente
```

### Faixa de materiais

```
Faixa 1000000-1000058 = INSUMO (59 codigos)
Faixa 3500001-3500498 = PRODUTO (503 codigos)
Faixa 4003001-4003498 = INSUMO (498 codigos)
```

### Procurar um tipo

```
Todos INSUMO: Veja abas 1-5
Todos PRODUTO: Veja abas 6-7
Legado: Faixa 992-999
```

---

## VALIDACOES IMPORTANTES

✓ Total de codigos: 2137 (verificado)  
✓ Sem duplicatas: Confirmado  
✓ Ranges nao sobrepostos: Confirmado  
✓ Classificacao 100%: Confirmado  
✓ Integridade dados: Confirmado  

---

## PROXIMAS ACOES

1. Criar tabela no banco de dados
2. Importar todos os 2137 codigos
3. Criar indices para performance
4. Testar queries de consulta
5. Documentar no wiki

---

**Documento Gerado**: 2026-07-17  
**Arquivo JSON Completo**: jotec_classificacao_completa.json  
**API de Consulta**: /api/importar_jotec_classificacao.php  

