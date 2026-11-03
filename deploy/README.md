# Despliegue en AWS EC2

La aplicación queda en **https://cotizaciones.trainingcorporation.com.co**. Corre en una sola EC2 con Docker Compose:

| Servicio | Qué hace |
|---|---|
| `caddy` | HTTPS con certificado de Let's Encrypt (lo pide y lo renueva solo) y redirección de http a https |
| `app` | Laravel y el frontend React ya compilado (Apache + PHP 8.4 con OPcache). Ejecuta las migraciones al arrancar |
| `worker` | Cola de Laravel: correos y exportaciones a Excel |
| `scheduler` | Tareas programadas: limpieza de exportaciones y adjuntos cada hora |
| `db` | MySQL 8.4. Solo es accesible dentro de Docker, no desde internet |

El frontend no tiene un servidor propio. Vite lo compila al construir la imagen y lo sirve el mismo contenedor `app`.

## 1. Qué máquina crear

Para unos **50 usuarios simultáneos**: **t3.medium** (2 vCPU, 4 GB de RAM) con 30 GB de disco gp3.

- 50 personas navegando y llenando el formulario generan unas 5 a 15 peticiones por segundo. Una t3.medium con OPcache atiende bastante más que eso. La prueba de carga confirma el número real.
- Una t3.small (2 GB) se queda corta: MySQL, Apache y la compilación de la imagen no caben con holgura.
- Use x86_64 (t3), no Graviton (t4g). Las dependencias de compilación del frontend están fijadas para x64.
- Costo aproximado en us-east-1: unos US$30/mes de la instancia, más ~US$2,4 del disco y ~US$3,6 de la IP pública. Confírmelo en la [calculadora de AWS](https://calculator.aws/).
- Las t3 son "burstable" y vienen en modo *unlimited*. Si la CPU pasa mucho tiempo por encima del 20 %, AWS cobra un extra en vez de frenar la máquina. Si las pruebas muestran la CPU alta de forma sostenida, pase a una t3.large o a una m7i.large.

## 2. Consola de AWS

1. **Región**: arriba a la derecha, elija **US East (N. Virginia) us-east-1**. Es la región más cercana a Colombia entre las económicas.
2. **EC2 → Instances → Launch instances**:
   - Name: `cotizaciones`
   - AMI: **Ubuntu Server 24.04 LTS**, arquitectura **64-bit (x86)**
   - Instance type: **t3.medium**
   - Key pair: **Create new key pair**, tipo RSA, formato `.pem`. Guarde el archivo; sin él no podrá entrar por SSH.
   - Network settings → **Edit** → Create security group `cotizaciones-sg` con estas reglas de entrada:
     | Tipo | Puerto | Origen |
     |---|---|---|
     | SSH | 22 | **My IP** (solo su IP, no 0.0.0.0/0) |
     | HTTP | 80 | 0.0.0.0/0 (Let's Encrypt lo necesita para emitir el certificado) |
     | HTTPS | 443 | 0.0.0.0/0 |
     | Custom UDP | 443 | 0.0.0.0/0 (opcional, HTTP/3) |
   - Configure storage: **30 GiB gp3**
   - Launch instance
3. **IP fija**: EC2 → **Elastic IPs** → *Allocate Elastic IP address* → *Allocate*. Luego *Actions → Associate Elastic IP address* → elija la instancia. Sin esto la IP cambia cada vez que se detiene la máquina.
4. **Copias del disco (opcional y recomendado)**: EC2 → *Lifecycle Manager* → crear una política de snapshots diarios del volumen de la instancia, con retención de 7 días.

## 3. DNS

En el proveedor donde se administra `trainingcorporation.com.co` (Route 53, GoDaddy, cPanel u otro), cree el registro:

| Tipo | Nombre | Valor | TTL |
|---|---|---|---|
| A | `cotizaciones` | la Elastic IP | 300 |

Compruebe que ya resuelve antes de desplegar:

```bash
dig +short cotizaciones.trainingcorporation.com.co
```

Si el dominio no apunta a la máquina, Let's Encrypt no emite el certificado. Tras varios intentos fallidos bloquea el dominio por un rato. `desplegar.sh` revisa esto y avisa antes de levantar los servicios.

## 4. Instalar en la máquina

Desde su computador:

```bash
chmod 400 cotizaciones.pem
ssh -i cotizaciones.pem ubuntu@<ELASTIC_IP>
```

En la EC2:

```bash
git clone https://github.com/AmazingJuan/reto-2-p2.git
cd reto-2-p2
git checkout development        # o la rama que vaya a producción

sudo ./deploy/preparar-servidor.sh   # Docker, swap, hora de Colombia, respaldo diario
exit                                 # salga y vuelva a entrar para usar docker sin sudo
```

Vuelva a entrar por SSH y continúe:

```bash
cd reto-2-p2
./deploy/configurar-env.sh      # crea .env: APP_KEY, contraseñas de MySQL, correo
nano .env                       # revise el bloque MAIL_*
./deploy/desplegar.sh           # construye y levanta todo (la primera vez tarda 5-10 min)
./deploy/datos-iniciales.sh     # usuarios admin, unidades, servicios y formulario de encuadre
```

Abra https://cotizaciones.trainingcorporation.com.co y luego:

1. Entre al panel con los usuarios de `database/seeders/DatabaseSeeder.php` y **cambie sus contraseñas**. Están escritas en el repositorio.
2. En **Configuración**, ponga el correo que recibe las solicitudes nuevas.
3. Envíe una solicitud de prueba y confirme que llegan los dos correos (al cliente y a gerencia).

### Correo

El `.env` viene preparado para Gmail (`smtp.gmail.com:587`). `MAIL_PASSWORD` debe ser una [contraseña de aplicación](https://support.google.com/accounts/answer/185833) de la cuenta, no la contraseña normal. AWS bloquea el puerto 25 de salida; los puertos 587 y 465 sí funcionan.

## 5. Operación diaria

| Tarea | Comando |
|---|---|
| Publicar cambios nuevos | `./deploy/desplegar.sh` (hace `git pull`, reconstruye y reinicia) |
| Estado de los servicios | `docker compose ps` |
| Logs en vivo | `docker compose logs -f app worker` |
| Log de Laravel | `docker compose exec app tail -n 100 storage/logs/laravel-$(date +%F).log` |
| Trabajos fallidos de la cola | `docker compose exec app php artisan queue:failed` y `queue:retry all` |
| Respaldo manual de la BD | `./deploy/backup-db.sh` (queda en `backups/`) |
| Consola de Laravel | `docker compose exec app php artisan tinker` |
| Uso de CPU/RAM | `docker stats` |

Si cambia algo del `.env`, aplíquelo con `docker compose up -d` (recrea los contenedores con los valores nuevos).

Los respaldos de `backups/` están en el mismo disco que la base de datos. Para protegerse si se pierde la máquina, active los snapshots del paso 2.4 o copie los archivos a S3.

## Notas

- `docker-compose.yml` reemplaza la configuración anterior con nginx y certbot. Caddy hace lo mismo y renueva el certificado solo.
- El flujo de GitHub Actions `deployment-branch.yml` publica una rama `deployment` sin el código fuente del frontend. El `Dockerfile` nuevo compila el frontend, así que hay que desplegar desde una rama normal (`development` o `main`), no desde `deployment`.
- Para probar el stack en su computador: `./deploy/configurar-env.sh localhost` en una copia del proyecto. Caddy usa entonces un certificado local.
