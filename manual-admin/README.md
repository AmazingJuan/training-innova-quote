# Manual del administrador de Training InnovaQuote

Documento LaTeX del manual de usuario del administrador. Se compila con **LuaLaTeX** y produce
`manual-administrador.pdf`. También se genera una versión editable en Word,
`manual-administrador.docx` (ver «Versión en Word»).

## Compilar

```bash
cd manual-admin
lualatex manual-administrador.tex
lualatex manual-administrador.tex   # segunda vez: índice, figuras y referencias
```

Las tipografías (Montserrat y Source Sans 3) y el ícono Font Awesome están en `fuentes/`.

## Estructura

| Archivo | Contenido |
|---|---|
| `manual-administrador.tex` | Documento principal. Aquí se cambian la versión, la fecha y el dominio (`\dominio`). |
| `estilo.tex` | Colores, tipografías, portada, cajas, botones y marcos de las capturas. |
| `capitulos/01…08-*.tex` | Un archivo por capítulo (el 07 es la capacidad y el 08 los apéndices). |
| `capturas/` | Las 39 capturas de pantalla, con el nombre que espera cada `\captura{…}`. |
| `herramientas/` | Scripts para volver a tomar las capturas. |
| `herramientas/word/` | Convertidor del manual a Word. |
| `fuentes/ttf/` | Las tipografías en TrueType, que son las que Word puede incrustar. |

Si falta una captura, el manual muestra en su lugar un recuadro punteado con el nombre del
archivo que debe guardar (`capturas/NN-NN-nombre.png`). Al compilar de nuevo, la imagen lo
reemplaza.

## Versión en Word

`manual-administrador.docx` es la versión editable del manual, para quien no trabaja con LaTeX.
Se genera a partir de los mismos capítulos, así que tiene el mismo contenido que el PDF.

En Word todo el texto se edita normalmente:

- Los títulos usan los estilos **Título 1, 2 y 3**, que se numeran solos (01, 1.1, 1.1.1).
- Los recuadros (Nota, Importante, Consejo, Resultado), las fichas de cada módulo y las tablas
  son tablas de Word.
- Las capturas y los diagramas son imágenes. Para cambiar una, clic derecho sobre ella y
  **Cambiar imagen**.
- El documento trae incrustadas las tipografías Montserrat y Source Sans 3, para que se vea
  igual aunque no estén instaladas.
- La portada y la contraportada tienen el fondo como imagen y el texto en cuadros editables.
- Las referencias en el texto («sección 2.5», «figura 3.1») son enlaces internos, pero su número
  está escrito a mano: si agrega o quita secciones, revíselas.

Después de editar, actualice el índice: clic derecho sobre el **Contenido** y luego
**Actualizar campos** → **Actualizar toda la tabla**. Los números de página del **Índice de
figuras** se actualizan seleccionando todo (Ctrl + E) y pulsando F9.

### Regenerar el Word

Cada vez que se cambie el LaTeX, compile primero el PDF (el Word usa su archivo `.aux`) y
luego:

```bash
python3 manual-admin/herramientas/word/generar.py
```

El script dibuja como imágenes los diagramas y fondos (`figuras.py`), lee los capítulos
(`latex.py`), arma el Word (`word.py`, `ooxml.py`, `imagenes.py`) y calcula los números de
página del índice convirtiendo el Word a PDF con LibreOffice. LibreOffice debe estar en
`herramientas/.entorno/lo`; si no está, el Word se genera igual, pero sin esos números. Para
descargarlo (unos 220 MB, sin instalar nada en el sistema):

```bash
cd manual-admin/herramientas
mkdir -p .entorno/lo && cd .entorno/lo
curl -fLO https://download.documentfoundation.org/libreoffice/stable/26.8.2/deb/x86_64/LibreOffice_26.8.2_Linux_x86-64_deb.tar.gz
tar xzf LibreOffice_*.tar.gz && for d in */DEBS/*.deb; do dpkg-deb -x "$d" raiz; done
```

Requiere Python 3 con `python-docx`, `lxml` y `Pillow`. El convertidor solo entiende los
comandos que usa este manual: si agrega un comando nuevo en LaTeX, `latex.py` avisa con un
error que dice cuál es.

## Capítulo de capacidad

Es un resumen corto en `capitulos/07-capacidad.tex`. Si repite las pruebas de carga con k6 y
cambian las cifras, actualice el recuadro «En resumen», la figura (marcas de 50, 100, 200 y 300
usuarios) y la conclusión.

## Volver a tomar las capturas

Las capturas se toman en un entorno **aparte** (no usa su `.env` ni su base de datos): una
instancia propia de MariaDB en el puerto 3307, datos de demostración ficticios y HTTPS local con
el dominio final `cotizaciones.trainingcorporation.com.co`. Requiere `chromium`, `socat`,
`openssl`, `php`, `node` y `ImageMagick`.

Desde la raíz del proyecto, cada comando en su propia terminal (los tres primeros se quedan
corriendo):

```bash
npm run build                                   # una vez, para compilar el frontend
bash manual-admin/herramientas/mariadb.sh       # 1. base de datos de las capturas
bash manual-admin/herramientas/preparar-bd.sh   # 2. crear la base y el usuario (una vez)
bash -c 'source manual-admin/herramientas/entorno.sh && php manual-admin/herramientas/datos-demo.php'   # 3. datos de demostración
bash manual-admin/herramientas/servidor.sh      # 4. aplicación
bash manual-admin/herramientas/tls.sh           # 5. HTTPS local
bash manual-admin/herramientas/cola.sh          # 6. cola (la exportación a Excel la necesita)

cd manual-admin/herramientas
npm install                                     # una vez
node capturar.mjs                               # todas, o: node capturar.mjs 03- 04-1
xvfb-run -a -s "-screen 0 2400x1900x24" node confirmacion.mjs   # 02-08, cuadro nativo del navegador
```

`datos-demo.php` reinicia la base de capturas cada vez que se ejecuta. Algunas capturas cambian
datos (por ejemplo, 03-04 envía una propuesta), así que vuelva a cargar los datos de
demostración antes de repetir el capítulo 3.

Las capturas no se pueden repetir en el servidor real: el script solo trabaja contra el entorno
local.
