"""Genera la versión en Word del manual: manual-admin/manual-administrador.docx

    python3 manual-admin/herramientas/word/generar.py

Pasos:
  1. Dibuja como PNG los diagramas TikZ y los fondos (figuras.py). Necesita el .aux del PDF.
  2. Lee los capítulos LaTeX y arma el .docx (latex.py + word.py).
  3. Si hay LibreOffice portable en herramientas/.entorno/lo, convierte el Word a PDF para
     calcular los números de página del índice y vuelve a generar el .docx con ellos.
"""
import re
import subprocess
import sys
from pathlib import Path

AQUI = Path(__file__).resolve().parent
sys.path.insert(0, str(AQUI))

import figuras  # noqa: E402
import latex  # noqa: E402
from word import Generador  # noqa: E402

MANUAL = latex.MANUAL
DESTINO = MANUAL / 'manual-administrador.docx'
ENTORNO = MANUAL / 'herramientas' / '.entorno'


def soffice():
    candidatos = sorted(ENTORNO.glob('lo/raiz/opt/libreoffice*/program/soffice'))
    return candidatos[-1] if candidatos else None


def a_pdf(docx, carpeta):
    lo = soffice()
    if not lo:
        return None
    subprocess.run([str(lo), f'-env:UserInstallation=file://{ENTORNO / "lo" / "perfil"}', '--headless',
                    '--convert-to', 'pdf', '--outdir', str(carpeta), str(docx)],
                   check=True, capture_output=True, timeout=600)
    return carpeta / (docx.stem + '.pdf')


def paginas_impresas(pdf, generador):
    """Número de página (el que se imprime) de cada título y figura, buscándolos en el PDF."""
    texto = subprocess.run(['pdftotext', '-layout', str(pdf), '-'], capture_output=True, text=True, check=True).stdout
    paginas = texto.split('\f')
    # La página 1 (arábiga) es la que tiene la banda del capítulo 1
    def normal(s):
        return ' '.join(s.replace('\u00a0', ' ').split())
    planas = [normal(p) for p in paginas]
    primero = next(i for i, p in enumerate(planas) if 'CAPÍTULO' in p and 'Introducción' in p and 'Contenido' not in p[:80])
    resultado = {}
    desde = primero
    lineas = [[normal(l) for l in p.splitlines()] for p in paginas]
    for nivel, numero, titulo, etiqueta in generador.entradas_indice:
        # El título está en una línea que empieza por su número (los largos siguen en la línea siguiente)
        inicio_titulo = normal(titulo)[:25]
        patron = re.compile(rf'^{re.escape(numero)}\s+{re.escape(inicio_titulo)}')
        for i in range(desde, len(planas)):
            if any(patron.match(l) for l in lineas[i]):
                resultado[etiqueta] = i - primero + 1
                desde = i
                break
    desde = primero
    for numero, titulo, etiqueta in generador.figuras:
        clave = f'Figura {numero}.'
        for i in range(desde, len(planas)):
            if clave in planas[i]:
                resultado[etiqueta] = i - primero + 1
                desde = i
                break
    return resultado


def main():
    figuras.main()
    bloques, lector = latex.leer_manual()
    Generador(bloques, lector).generar(DESTINO)
    carpeta = ENTORNO / 'word' / 'pdf'
    carpeta.mkdir(parents=True, exist_ok=True)
    pdf = a_pdf(DESTINO, carpeta)
    if pdf:
        bloques, lector = latex.leer_manual()
        g = Generador(bloques, lector)
        g.paginas = paginas_impresas(pdf, g)
        g.generar(DESTINO)
        a_pdf(DESTINO, carpeta)
        print(f'Números de página: {len(g.paginas)} de {len(g.entradas_indice) + len(g.figuras)}')
    print(DESTINO)


if __name__ == '__main__':
    main()
