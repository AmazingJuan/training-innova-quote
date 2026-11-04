"""Convierte en PNG los dibujos del manual que Word no puede dibujar por sí mismo.

- Los diagramas TikZ de los capítulos (cada \\begin{figure} con un tikzpicture), con el mismo
  estilo.tex del PDF para que queden idénticos.
- Los fondos de la portada, la contraportada y la banda azul de los capítulos (sin texto: el
  texto va en el Word, para que se pueda editar).

    python3 figuras.py      (desde cualquier carpeta)

Deja las imágenes en herramientas/.entorno/word/figuras/<nombre>.png
"""
import re
import subprocess
import sys
from pathlib import Path

MANUAL = Path(__file__).resolve().parents[2]
SALIDA = MANUAL / 'herramientas' / '.entorno' / 'word'
FIGURAS = SALIDA / 'figuras'
DPI = 300


def llaves(texto, i):
    """Devuelve el índice justo después del grupo {…} que empieza en texto[i] == '{'."""
    nivel = 0
    while i < len(texto):
        c = texto[i]
        if c == '\\':
            i += 2
            continue
        if c == '{':
            nivel += 1
        elif c == '}':
            nivel -= 1
            if nivel == 0:
                return i + 1
        i += 1
    raise ValueError('llave sin cerrar')


def sin_comentarios(texto):
    # Como en TeX: el comentario también se come el salto de línea y la sangría de la línea siguiente.
    return re.sub(r'(?<!\\)%[^\n]*\n[ \t]*', '', texto)


def definiciones(texto):
    """Los \\newcommand y \\definecolor de un capítulo, con sus argumentos completos."""
    salida = []
    for m in re.finditer(r'\\(newcommand|definecolor)', texto):
        i = m.end()
        fin = i
        # \newcommand{\nombre}[n]{cuerpo}  ·  \definecolor{nombre}{modelo}{valor}
        partes = 3 if m.group(1) == 'definecolor' else 2
        while partes:
            while texto[fin].isspace():
                fin += 1
            if texto[fin] == '[':
                fin = texto.index(']', fin) + 1
                continue
            fin = llaves(texto, fin)
            partes -= 1
        salida.append(texto[m.start():fin])
    return salida


def figuras_tikz():
    """(nombre, código TikZ) de cada figura dibujada en los capítulos."""
    figuras, defs = [], []
    for archivo in sorted((MANUAL / 'capitulos').glob('0*.tex')):
        texto = sin_comentarios(archivo.read_text(encoding='utf-8'))
        defs += definiciones(texto)
        for bloque in re.findall(r'\\begin\{figure\}.*?\\end\{figure\}', texto, flags=re.S):
            if '\\begin{tikzpicture}' not in bloque:
                continue
            etiqueta = re.search(r'\\label\{fig:([^}]*)\}', bloque).group(1)
            codigo = bloque[bloque.index('\\begin{tikzpicture}'):bloque.rindex('\\end{tikzpicture}') + len('\\end{tikzpicture}')]
            figuras.append((etiqueta, codigo))
    return figuras, defs


def preambulo():
    principal = (MANUAL / 'manual-administrador.tex').read_text(encoding='utf-8')
    datos = '\n'.join(re.findall(r'^\\newcommand\{\\(?:versiondoc|fechadoc|elaboradopor|dominio)\}.*$', principal, flags=re.M))
    return f'\\documentclass[11pt, a4paper]{{report}}\n{datos}\n\\input{{estilo}}\n'


def para_word(codigo):
    """En Word no hay números de página fijos: las referencias de página pasan a número de sección."""
    codigo = codigo.replace('pág.~\\pageref*', '§\\,\\ref*').replace('(pág.~\\pageref*', '(§\\,\\ref*')
    return codigo.replace('\\pageref*', '§\\,\\ref*')


FONDOS = {
    # Portada: campo azul con los anillos, iconos de «En este manual» y franja con el logo.
    'portada': r'''
\begin{tikzpicture}
  \useasboundingbox (0,0) rectangle (21,29.7);
  \fill[white] (0,0) rectangle (21,29.7);
  \fill[azul] (0,12.4) rectangle (21,29.7);
  \begin{scope}
    \clip (0,12.4) rectangle (21,29.7);
    \pic[opacity=0.30] at (19.6,14.2) {anillo={6.0cm/1.7pt/cielo}};
    \pic[opacity=0.65] at (20.6,27.0) {anillo={2.6cm/1.1pt/azulvivo}};
  \end{scope}
  \fill[cielo] (2.4,18.2) rectangle (4.9,18.32);
  \foreach \x/\y/\i in {2.4/9.7/F084, 2.4/8.5/F70E, 2.4/7.3/F0E8, 11.0/9.7/F013, 11.0/8.5/F2F5, 11.0/7.3/F080} {
    \node[fill=azul, text=white, minimum size=0.72cm, inner sep=0pt, rounded corners=2pt,
      font=\fontsize{10}{10}\selectfont] at (\x+0.36,\y) {\ico{\i}};
  }
  \fill[noche] (0,0) rectangle (21,2.7);
  \node[anchor=west, inner sep=0pt] at (2.4,1.35) {\includegraphics[height=1.15cm]{imagenes/logo-blanco.png}};
\end{tikzpicture}''',
    # Contraportada: azul marino, anillos y logo grande.
    'contraportada': r'''
\begin{tikzpicture}
  \useasboundingbox (0,0) rectangle (21,29.7);
  \fill[noche] (0,0) rectangle (21,29.7);
  \begin{scope}
    \clip (0,0) rectangle (21,29.7);
    \pic[opacity=0.35] at (10.5,15.6) {anillo={6.2cm/1.6pt/cielo}};
    \pic[opacity=0.5] at (19.4,3.0) {anillo={3.2cm/1.1pt/azulvivo}};
    \pic[opacity=0.5] at (1.4,26.8) {anillo={2.4cm/1.1pt/azulvivo}};
  \end{scope}
  \node at (10.5,15.6) {\includegraphics[width=6.4cm]{imagenes/logo-blanco.png}};
\end{tikzpicture}''',
    # Banda azul de la primera página de cada capítulo (21 × 7 cm).
    'banda': r'''
\begin{tikzpicture}
  \useasboundingbox (0,0) rectangle (21,7);
  \fill[azul] (0,0) rectangle (21,7);
  \begin{scope}
    \clip (0,0) rectangle (21,7);
    \pic[opacity=0.45] at (17.9,4.3) {anillo={4.3cm/1.5pt/cielo}};
    \pic[opacity=0.7] at (20.5,0.5) {anillo={1.9cm/1.1pt/azulvivo}};
  \end{scope}
\end{tikzpicture}''',
}


def compilar(nombre, cuerpo, borde):
    """Compila un documento con un dibujo por página (paquete preview) y lo pasa a PNG."""
    tex = SALIDA / f'{nombre}.tex'
    tex.write_text(
        preambulo()
        + f'\\usepackage[active,tightpage]{{preview}}\n\\setlength\\PreviewBorder{{{borde}}}\n'
        + '\\makeatletter\\input{' + str(SALIDA / 'etiquetas.aux') + '}\\makeatother\n\\begin{document}\n'
        + cuerpo + '\n\\end{document}\n',
        encoding='utf-8',
    )
    for _ in range(2):
        r = subprocess.run(['lualatex', '-interaction=nonstopmode', '-halt-on-error', f'-output-directory={SALIDA}', str(tex)],
                           cwd=MANUAL, capture_output=True, text=True)
        if r.returncode:
            sys.exit(f'Error al compilar {nombre}:\n' + '\n'.join(r.stdout.splitlines()[-25:]))
    return SALIDA / f'{nombre}.pdf'


def a_png(pdf, nombres):
    for pagina, nombre in enumerate(nombres, start=1):
        destino = FIGURAS / nombre
        subprocess.run(['pdftoppm', '-r', str(DPI), '-f', str(pagina), '-l', str(pagina), '-png', '-singlefile',
                        str(pdf), str(destino)], check=True)


def main():
    FIGURAS.mkdir(parents=True, exist_ok=True)
    aux = MANUAL / 'manual-administrador.aux'
    if not aux.exists():
        sys.exit('Compile antes el PDF (lualatex manual-administrador.tex): hace falta el .aux con las referencias.')
    etiquetas = [l for l in aux.read_text(encoding='utf-8').splitlines() if l.startswith('\\newlabel')]
    (SALIDA / 'etiquetas.aux').write_text('\n'.join(etiquetas) + '\n', encoding='utf-8')

    figuras, defs = figuras_tikz()
    cuerpo = '\n'.join(para_word(d) for d in defs) + '\n'
    cuerpo += '\n'.join(f'\\begin{{preview}}{para_word(c)}\\end{{preview}}' for _, c in figuras)
    a_png(compilar('figuras', cuerpo, '6pt'), [n for n, _ in figuras])

    cuerpo = '\n'.join(f'\\begin{{preview}}{c}\\end{{preview}}' for c in FONDOS.values())
    a_png(compilar('fondos', cuerpo, '0pt'), list(FONDOS))

    for png in sorted(FIGURAS.glob('*.png')):
        print(png.name)


if __name__ == '__main__':
    main()
