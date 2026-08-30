#!/usr/bin/env python3
"""Monta o corpus de URLs de QA de forma DETERMINISTICA (mesmo corpus em toda execucao)."""
import os, sys
BASE = os.path.dirname(os.path.abspath(__file__))

CRITICAS = [
    "https://cetrus.com.br/",
    "https://cetrus.com.br/vitrine/",
    "https://cetrus.com.br/especialidades/",
    "https://cetrus.com.br/curriculo/",
    "https://cetrus.com.br/conteudos-gratuitos/",
    "https://cetrus.com.br/institucional/",
    "https://cetrus.com.br/unidades/",
    "https://cetrus.com.br/area-do-aluno/",
    "https://cetrus.com.br/central-de-apoio-ao-aluno/",
    "https://cetrus.com.br/sitemap/",
    "https://cetrus.com.br/politica_privacidade/",
    "https://cetrus.com.br/formacao-inicial-usg/",
    "https://cetrus.com.br/cetrus-para-empresas-b2b/",
    "https://cetrus.com.br/monitoria/",
    "https://cetrus.com.br/fale-pelo-whatsapp/",
    # produtos com caracteristicas distintas (turmas, EAD, fellowship, pacote)
    "https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/",
    "https://cetrus.com.br/cursos/ultrassonografia-em-ginecologia-e-obstetricia/",
    "https://cetrus.com.br/curriculo/dr-anselmo-carmo/",
]

def amostra(arquivo, n):
    """Amostra uniforme e estavel: ordena e pega n itens espacados."""
    caminho = os.path.join(BASE, arquivo)
    if not os.path.exists(caminho):
        return []
    urls = sorted({l.strip() for l in open(caminho) if l.strip()})
    if not urls:
        return []
    if len(urls) <= n:
        return urls
    passo = len(urls) / n
    return [urls[int(i * passo)] for i in range(n)]

def corpus():
    u = list(CRITICAS)
    u += amostra("urls-page.txt", 21)
    u += amostra("urls-product.txt", 20)
    u += amostra("urls-curriculo.txt", 12)
    u += amostra("urls-v2_especialidade.txt", 10)
    u += amostra("urls-v2_cidade.txt", 5)
    u += amostra("urls-v2_modalidade.txt", 3)
    u += amostra("urls-polo-de-treinamento.txt", 4)
    u += amostra("urls-product_cat.txt", 1)
    vistos, saida = set(), []
    for x in u:
        x = x.rstrip()
        if x not in vistos:
            vistos.add(x); saida.append(x)
    return saida

if __name__ == "__main__":
    for x in corpus():
        print(x)
