"""Ayuda para editar los capítulos: reemplaza texto ignorando diferencias de saltos de línea y espacios."""
import re, sys

class Editor:
    def __init__(self, ruta):
        self.ruta = ruta
        self.s = open(ruta, encoding='utf-8').read()

    def rep(self, old, new, count=1):
        patron = r'\s+'.join(re.escape(t) for t in old.split())
        encontrados = len(re.findall(patron, self.s))
        if encontrados == 0:
            sys.exit(f'NO ENCONTRADO en {self.ruta}: {old[:80]!r}')
        self.s = re.sub(patron, lambda m: new, self.s, count=count)

    def guardar(self):
        open(self.ruta, 'w', encoding='utf-8').write(self.s)
        print('ok', self.ruta)
