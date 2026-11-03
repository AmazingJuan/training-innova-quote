# Pruebas de capacidad con k6

Estas pruebas responden "¿cuántos usuarios aguanta?" midiendo el sitio público, que es donde llega el tráfico de clientes:

- **Visitante (90 %)**: inicio → `/cotizar` → formulario de una unidad, con pausas como las de una persona.
- **Solicitante (10 %)**: abre el formulario, lo llena siguiendo las ramas de las preguntas y lo **envía** (`POST /cotizar`). Esto escribe en la base de datos y encola los correos.

Cada visita descarga también el JS y el CSS, como un navegador que entra por primera vez.

## Antes de empezar (importante)

Haga las pruebas **antes de salir a producción**, porque cada solicitud enviada crea un cliente y una solicitud reales.

1. En la EC2, desactive el envío real de correos. Así no se mandan miles de correos a direcciones `@example.com` ni a gerencia:
   ```bash
   sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env && docker compose up -d
   ```
2. Ejecute k6 **desde otra máquina**, nunca desde la misma EC2, porque k6 le quitaría CPU a la app. Empiece desde su computador. Si su internet se vuelve el límite (vea "Cómo leer los resultados"), cree una EC2 temporal t3.medium en la misma región, instale k6, corra desde ahí y bórrela al terminar.
3. Al terminar, deje la base limpia y vuelva a activar el correo:
   ```bash
   ./deploy/reiniciar-datos.sh          # borra todo y recarga los datos iniciales
   sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=smtp/' .env && docker compose up -d
   ```

## Cómo correrlas

Instalación de k6: https://grafana.com/docs/k6/latest/set-up/install-k6/

```bash
./load-tests/correr.sh humo            # 2 min: confirma que todo funciona (córralo siempre primero)
./load-tests/correr.sh carga 50        # 15 min: 50 usuarios simultáneos
./load-tests/correr.sh estres 50       # 21 min: 50 → 100 → 200 → 300 → 400 usuarios
./load-tests/correr.sh pico 50         # 6 min: salto repentino a 200 usuarios
./load-tests/correr.sh rps             # 15 min: peticiones por segundo crecientes hasta 150
```

Cada ejecución guarda en `load-tests/resultados/` un informe **HTML con gráficas en el tiempo** y un resumen JSON. Mientras corre, hay un panel en vivo en http://localhost:5665.

| Variable | Por defecto | Para qué |
|---|---|---|
| `BASE_URL` | `https://cotizaciones.trainingcorporation.com.co` | Otro destino |
| `PCT_SOLICITUDES` | `10` | % de usuarios que envían la solicitud |
| `ASSETS` | `1` | `0` para no descargar JS/CSS |
| `PENSAR` | `1` | Escala de las pausas (`0.2` = usuarios 5 veces más rápidos) |
| `ADJUNTO_KB` | `0` | Adjunta un PDF de ese tamaño a cada solicitud |
| `MAX_RPS` | `150` | Techo del perfil `rps` |
| `DURACION` | `60m` | Duración del perfil `resistencia` |
| `INSECURE` | `0` | `1` acepta certificados no válidos (pruebas locales) |

Ejemplo: `PCT_SOLICITUDES=30 ADJUNTO_KB=2000 ./load-tests/correr.sh carga 50`

## Orden recomendado

1. `humo`: debe pasar todo en verde. La primera ejecución después de reiniciar los contenedores puede salir lenta porque PHP está "en frío"; repítala.
2. `carga 50`: confirma que aguanta lo que espera el cliente.
3. `estres 50`: muestra hasta dónde llega y qué se rompe primero.
4. `rps`: da el número de peticiones por segundo que soporta.
5. Opcional: `pico 50` y `resistencia 50`.

Mientras corre cada prueba, tenga abierta una sesión SSH en la EC2 con `docker stats` para ver qué contenedor se queda sin CPU o RAM (`app` = PHP, `db` = MySQL).

## Cómo leer los resultados

Una prueba **aguanta** si se cumplen los umbrales (✓ en el resumen):

- menos de 1 % de errores (`http_req_failed`),
- el 95 % de las páginas en menos de 800 ms (`http_req_duration{name:GET ...}` p(95)),
- el 95 % de los envíos en menos de 2 s (`POST /cotizar`).

En `estres`, abra el informe HTML y busque el escalón donde la latencia p95 se dispara o aparecen errores. El escalón anterior es la capacidad. La prueba se detiene sola si más del 10 % de las peticiones fallan.

Para saber dónde está el cuello de botella:

| Síntoma | Causa probable |
|---|---|
| `app` con CPU ~200 % en `docker stats` | PHP saturado: más vCPU (t3.large / c7i.large) |
| `db` con CPU alta | MySQL saturado. Las sesiones y la caché van a la BD; pasarlas a Redis ayuda |
| Latencia sube pero la CPU del servidor está baja | Se llenaron los procesos de Apache (`APACHE_MAX_WORKERS` en `.env`) o el límite es la conexión de quien corre k6 |
| `data_received` plano en el informe y la CPU de la EC2 baja | Su internet es el límite. Pruebe con `ASSETS=0` o corra k6 desde una EC2 |
| Errores 502/504 | `app` reiniciándose o sin memoria: revise `docker compose logs app` |

## Qué decirle al cliente

Con los números de `carga`, `estres` y `rps` se puede responder así:

> "En el servidor actual (t3.medium: 2 vCPU, 4 GB) la aplicación atiende **N usuarios simultáneos** con el 95 % de las páginas en menos de **X ms** y sin errores, unas **R peticiones por segundo**. Por encima de eso se satura **[PHP / MySQL]**; se amplía cambiando el tamaño de la máquina, sin tocar el código."
