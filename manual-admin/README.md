# Manual del administrador de Training InnovaQuote

Documento LaTeX del manual de usuario del administrador. Se compila con **LuaLaTeX** y produce
`manual-administrador.pdf`. La carpeta está en el `.gitignore` del proyecto.

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

Si falta una captura, el manual muestra en su lugar un recuadro punteado con el nombre del
archivo que debe guardar (`capturas/NN-NN-nombre.png`). Al compilar de nuevo, la imagen lo
reemplaza.

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
