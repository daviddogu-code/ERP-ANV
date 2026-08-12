# Backlog — ERP ANV

> Registro vivo de tareas pendientes y de decisiones ya tomadas.
> Escrito en español porque el lector principal es el dueño del proyecto.
> Las otras notas de `docs/` están en inglés y son documentación técnica; esta no.
>
> Última actualización: 2026-08-12.

## Cómo usar este archivo

Se lee y se edita desde tres sitios: en Cursor (`docs/backlog.md`), en el Explorador
de Windows (`C:\laragon\www\tec\docs\backlog.md`) o desde el móvil en GitHub, en
`github.com/daviddogu-code/ERP-ANV`, carpeta `docs`.

Cuando una tarea se termina, se mueve a la sección "Hecho" con la fecha y una línea
explicando por qué se hizo. Esa explicación es lo que evita rehacer trabajo o deshacer
por error algo que estaba puesto a propósito.

---

## Con fecha límite

- **Antes del 3 de octubre de 2026** — confirmar en Namecheap (Profile → Billing) que la
  tarjeta guardada sigue vigente. Ese día renueva `anvfightgear.com`, que es el dominio
  sobre el que va a ir el ERP y la web pública. La renovación automática está activada,
  pero no sirve de nada si la tarjeta ha caducado.

## En curso

- **Crear el servidor de Singapur.** El ensayo del 2026-08-12 confirmó que la receta de
  despliegue funciona, así que ya no queda nada bloqueando. El plan cerrado está más abajo,
  en "Despliegue a DigitalOcean". El siguiente paso lo tiene que dar el dueño: crear el
  droplet desde el panel de DigitalOcean.

## Seguridad

- **DKIM y DMARC en `anvfightgear.com`.** Hoy solo existe el registro SPF. Sin los otros
  dos, los correos de la fábrica tienen más papeletas de acabar en spam y cualquiera puede
  suplantar el dominio. Se vuelve crítico antes de que ningún agente automático empiece a
  escribir a clientes. DKIM se genera en el panel de Google Workspace y se pega en
  Namecheap → Advanced DNS; DMARC es un registro TXT que se escribe a mano.
- **Guardar los diez códigos de recuperación de Namecheap** fuera del móvil (Drive o
  impresos). Namecheap no permite dos métodos de 2FA a la vez, así que si se pierde el
  teléfono esos códigos son la única vía de entrada.
- **Cortafuegos en el servidor nuevo.** La cuenta de DigitalOcean no tiene ninguno
  configurado. Son gratuitos. Hay que dejarlo puesto antes de subir datos reales. Las
  reglas concretas están abajo, en "Despliegue a DigitalOcean".

## Despliegue a DigitalOcean

Plan cerrado el 2026-08-12. El procedimiento técnico paso a paso vive en
`modules/custom/tec_production/README.md`, sección "Deployment procedure". Aquí están solo
las decisiones y el porqué de cada una.

### Lo que se contrata

Un droplet de **2 GB de RAM, 1 CPU y 50 GB de disco en Singapur (`sgp1`), 12 USD/mes**, con
copias automáticas **diarias**, que cuestan un 30% más (3,60 USD). Total **15,60 USD al
mes**. No se suma a la factura: sustituye al de Nueva York, que cuesta 32.

Singapur en vez de Nueva York porque la fábrica está en Tailandia. Hoy cada clic cruza medio
planeta y vuelve, unos 250 ms de ida y vuelta; desde Singapur el viaje es diez veces más
corto. En un ERP, donde se hacen cientos de clics al día, eso se nota en el ánimo de quien
lo usa.

El tamaño no lo marcan los tres empleados que van a usarlo, sino lo que ocupa el software
parado. Ubuntu se lleva unos 180 MB solo por estar encendido y MySQL arranca reservando unos
450 MB para sus cachés, así que en un droplet de 1 GB quedan menos de 400 MB libres, y cada
petición de PHP necesita entre 120 y 150 MB para montar los 197 módulos activos. Salen dos
peticiones y media simultáneas. Y los empleados no son los únicos que piden cosas: el cron
de Drupal, las reglas de ECA y la generación de miniaturas pesan igual que una persona, solo
que no están sentadas en un escritorio. Cuando falta memoria Linux no muestra un error
educado, mata el proceso a media faena, que es el fallo más difícil de diagnosticar que
existe. Aparte, `composer install` necesita cerca de 1 GB él solo, así que en un servidor de
1 GB el despliegue falla directamente.

Es más pequeño que el de Nueva York (2 vCPU / 4 GB / 120 GB) a propósito: aquel se dimensionó
sin datos y nunca se llegó a usar. En DigitalOcean la memoria y la CPU se pueden **bajar**
igual que subir mientras no se toque el disco, pero el disco solo crece, nunca mengua. Por
eso se arranca en 2 GB, se deja correr un mes con la fábrica usándolo de verdad y se decide
con el consumo real.

### Qué se instala dentro

Ubuntu 24.04 LTS (con soporte hasta 2029), Apache, PHP 8.3 y MySQL 8.4. La regla es
aburrirse a propósito: las mismas versiones que Laragon, porque cada diferencia entre el PC
y el servidor es un fallo que aparece solo en producción.

Apache y no nginx, aunque nginx sea más rápido, porque Drupal trae sus reglas de seguridad
escritas en ficheros `.htaccess` que Apache lee solo; con nginx habría que traducirlas a
mano y un olvido ahí es un agujero. Con tres usuarios la diferencia de velocidad no se nota.

MySQL 8.4 desde el repositorio oficial de Oracle, no el 8.0 que trae Ubuntu, para que
coincida con el 8.4.3 de local. Restaurar una copia de una versión nueva en una vieja
funciona casi siempre, y ese "casi" no compensa.

`apt-get install patch` es obligatorio. Sin ese programa el parche de `inline_entity_form`
falla en silencio; ver el ensayo del 2026-08-12 en "Hecho".

### Cortafuegos

Dos capas, las dos gratis: el de DigitalOcean filtra fuera de la máquina y `ufw` dentro, por
si alguien toca el de fuera por error. La misma regla en ambos: entran solo el puerto 443
(web segura), el 80 (que únicamente redirige al 443 y sirve para renovar el certificado) y
el 22 (acceso remoto). Todo lo demás, cerrado.

El 22 es el que da miedo, porque los robots lo escanean a todas horas. Se cierra bien así:
entrada solo con llave criptográfica, contraseñas desactivadas del todo, `root` sin poder
entrar directamente y `fail2ban` baneando a quien falle varias veces. Con eso, aunque el
puerto esté abierto al mundo, no hay nada que adivinar.

El ERP queda accesible desde cualquier sitio, sin restringir por dirección IP, porque los
empleados acabarán entrando desde el móvil con datos y llamando un domingo. Lo que lo
protege es el login, y más adelante conviene añadirle verificación en dos pasos también
dentro de Drupal.

### Copias

Las diarias de DigitalOcean cubren el desastre grande. Encima de eso, un volcado de la base
de datos cada noche guardado en el propio servidor, conservando dos semanas. Ocupa nada y
sirve para lo que de verdad pasa a diario, que no es que se incendie el centro de datos sino
que alguien borre algo sin querer y quieras recuperar el estado de ayer sin restaurar la
máquina entera. Sacar esos volcados fuera del servidor, a Drive, queda para una segunda fase.

### El orden

1. Crear el droplet en el panel de DigitalOcean. **Lo tiene que hacer el dueño**, son cinco
   minutos.
2. Dejarlo configurado: sistema, Apache, PHP, MySQL, `patch`, cortafuegos y accesos.
3. Subir el ERP siguiendo el procedimiento del README: el código desde GitHub, la base de
   datos y los archivos desde los zips, y `settings.php` escrito a mano.
4. Añadir en Namecheap → Advanced DNS un registro A que apunte `erp.anvfightgear.com` a la
   IP nueva. No hay que tocar `www`, ni el redireccionamiento, ni nada del correo.
5. Instalar el certificado con Let's Encrypt para que sea `https://` y no dé avisos de sitio
   inseguro.
6. Probarlo unos días **con el de Nueva York todavía encendido**, por si acaso.
7. Destruir el de Nueva York. Durante el solape se pagan los dos, pero el cobro es por horas.

Crear el servidor son veinte minutos. Dejarlo bien, una tarde.

### Lo que el ensayo no pudo probar

Se hizo sobre Windows y por HTTP, así que esto sigue sin verificar y hay que resolverlo en
el servidor: HTTPS, la programación del cron (Ultimate Cron iba con once tareas atrasadas),
el envío de correo —DigitalOcean bloquea el puerto 25 en las cuentas nuevas, así que hará
falta un relé SMTP, probablemente el de Google Workspace—, los permisos de las carpetas de
archivos, y el ajuste de `opcache`.

## Producto

- **Lista de compra sugerida por proveedor**, con el total de cada uno y aviso de los
  umbrales de envío gratuito. Boceto ya acordado, falta montarlo.
- **Avance automático del estado del pedido** cuando "Remaining" llega a cero. Decidido
  dejarlo desconectado de momento; se puede conectar más adelante sin rehacer nada.
- **Packing lists.**
- **Facturas**, con IVA y sin IVA.
- **Web pública de `anvfightgear.com`**, de presentación de la fábrica OEM. Hoy el dominio
  muestra la página de aparcamiento de Namecheap.
- **Portal de clientes**: que entren con su cuenta, vean sus productos y hagan sus propios
  pedidos.
- **Agentes.** Atención al cliente en los grupos de WhatsApp y en el correo. Pedidos
  automáticos a proveedores locales por los grupos de LINE.

## Mantenimiento y deuda técnica

- **Subir a Google Drive** los dos zips de copia de seguridad del 2026-08-12.
- **Borrar `c:\laragon\backups\nested-git-20260812`** dentro de unas semanas, cuando esté
  claro que la conversión de los módulos no dio problemas.
- **Borrar el snapshot `actafight.com-1755603370919`** cuando el ERP nuevo lleve un mes
  funcionando en Singapur.
- **Borrar el snapshot `thailivestream-final-2026-08-12`** dentro de tres meses si no se
  retoma ese proyecto. Cuesta 0,56 dólares al mes y es lo único que queda de esa web.
- **`composer update` no se puede ejecutar en este proyecto.** Hay conflictos entre
  dependencias fijadas a Drupal 10.2.4 y otras que ya piden 10.3 u 11. Un intento llegó a
  desinstalar `drupal/inline_entity_form` por su cuenta. Hoy se sortea usando solo
  `composer install` y operaciones dirigidas. Algún día habrá que sanearlo.
- **Cuatro módulos viven fuera de Composer**: `pdf_serialization`, `views_entity_form_field`,
  `quicktabs` e `integer_to_decimal`. No están en `composer.json` ni en `composer.lock`, se
  descargaron a mano. Llegan al servidor solo a través del repositorio. Dos de ellos llevan
  parches aplicados a mano (ver "Hecho").
- **`views_aggregator` 2.1.1 dice que necesita Drupal 10.3 u 11** y el sitio corre 10.2.4, así
  que el informe de estado lo marca como incompatible. Hoy funciona, pero es otro de los
  módulos instalados a mano y conviene resolverlo antes de que se convierta en un incidente.
- **Estudiar `composer-exit-on-patch-failure`** en `composer.json`, para que un parche que no
  se aplica corte la instalación en voz alta en lugar de pasar desapercibido, como pasó en el
  ensayo.
- **Mirar las versiones móviles de GitHub y Cursor.** GitHub tiene aplicación de iOS y
  Android, con la que se puede leer y editar este backlog desde el teléfono. Cursor tiene
  agentes en la nube que se lanzan desde el navegador del móvil. Ninguna de las dos es
  urgente.
- **El editor guarda a veces en UTF-16 en vez de UTF-8**, y eso rompe cualquier fichero PHP,
  CSS, JS o Markdown que toque. Ha pasado ya media docena de veces, incluida una al escribir
  este mismo archivo. De momento se detecta y se corrige a mano después de cada edición.

---

## Hecho

### 2026-08-12 — Ensayo de despliegue en local

Se reconstruyó el ERP entero desde cero en una carpeta desechable, usando solo tres
ingredientes: el repositorio de GitHub, el volcado de la base de datos y el zip del proyecto.
Funcionó: el sitio reconstruido servía los pedidos con sus totales calculados, el tablero de
stock con sus columnas, el registro de producción y las imágenes, sin un solo error de PHP.

Pero no salió limpio, y de ahí salieron cuatro hallazgos que se habrían pagado caros en el
servidor.

**`composer install` miente.** Hicieron falta tres pasadas. La primera murió con una descarga
corrupta. La segunda informó de 243 instalaciones y terminó con éxito, pero había dejado 45
de 156 carpetas de módulos vacías y `themes/contrib` sin nada. La tercera instaló los 157
paquetes que faltaban. La regla que queda es no creerse el mensaje final: hay que contar las
carpetas.

**113 archivos del núcleo de Drupal se copiaron con 0 bytes**, entre ellos `backbone.js` y
tres piezas del editor de texto, que rompen la administración. El archivo descargado estaba
dañado y se reutilizaba en cada intento; encima machacó con ficheros vacíos otros que venían
bien desde GitHub.

**El parche de `inline_entity_form` no se aplica nunca.** Falló las cuatro veces. Composer,
para parchear, primero *borra* el módulo y lo reinstala; luego intenta aplicar el parche con
Git, que lo descarta en silencio porque el módulo vive dentro del propio repositorio del
proyecto; y después recurre al programa `patch`, que si no está instalado deja el módulo sin
parchear mientras Composer termina diciendo que todo fue bien. Ese borrado previo es el
mecanismo del incidente antiguo en el que Composer "desinstaló" el módulo por su cuenta.

**Faltaba un ingrediente en la receta:** `sites/default/private` tiene 1.384 archivos reales
y no está en GitHub, solo dentro del zip del proyecto. Sin él se rompen las descargas de
archivos privados.

Los dos primeros fallos huelen a antivirus de Windows entrometiéndose mientras Composer
descomprime, así que probablemente no se repitan en Ubuntu. Los dos últimos sí van a
aparecer, y ya están cubiertos en el procedimiento.

Aguantaron bien la protección del `.htaccess` (intacta tras cuatro pasadas de Composer, o sea
que el arreglo del 11 de agosto funciona), los cuatro módulos que no gestiona Composer, los
diecisiete convertidos esa misma mañana, y la base de datos, que se importó fiel: 123
pedidos, 4.987 artículos, 214 productos, 583 líneas.

El procedimiento definitivo, con las trampas y una lista de comprobación con cifras de
referencia, quedó escrito en `modules/custom/tec_production/README.md`. Del ensayo no queda
nada: carpeta, base de datos de prueba y configuración de Apache borradas, y `tec.test`
intacto.

### 2026-08-12 — El proyecto entero en GitHub

Se creó el repositorio privado `github.com/daviddogu-code/ERP-ANV` y se subió el proyecto
completo, 17.456 archivos. La identidad de Git (`daviddogu-code` con el correo oculto de
GitHub) está configurada **solo en este repositorio**, no en el Windows entero, para que
otros proyectos puedan llevar la suya. La rama se llama `main`.

Quedan fuera del repositorio, a propósito, el motor de Drupal (`/core` y `/vendor`, se
recuperan con `composer install`), las imágenes de `sites/default/files` y la base de datos.
Esos dos últimos están en los zips de copia de seguridad.

También se bloquearon en `.gitignore` dos volcados de base de datos con nombres corruptos,
una carpeta `backups/` dentro del proyecto, restos de dos restauraciones antiguas y cinco
scripts `tmp_` de pruebas.

### 2026-08-12 — Diecisiete módulos que llegaban vacíos

Diecisiete módulos contrib se habían instalado desde código fuente, así que cada uno traía
su propio historial de Git dentro y el repositorio padre solo guardaba una referencia. Un
clon habría producido diecisiete carpetas vacías, entre ellas `eck`, sobre el que están
construidos todos los tipos de datos a medida del ERP, y `eca`, que mueve las transacciones
de inventario. Ahora se guardan como archivos normales.

Dos de ellos llevaban parches hechos a mano que no existían en ningún otro sitio:
`views_entity_form_field` tiene el arreglo de AJAX del caso #2998721-64, y
`pdf_serialization` lleva los parches 4 y 6 más una clase `PdfManagerInterface` que el
módulo original no incluye. Ninguno de los dos lo gestiona Composer, así que no se pueden
registrar en `composer.json`; su única protección es estar en el repositorio.

Los diecisiete historiales internos **no se borraron**: están en
`c:\laragon\backups\nested-git-20260812`. Si algún día hiciera falta deshacer esto, se
devuelven a su sitio.

En `composer.json` se fijó `preferred-install` a `dist` para que una instalación futura no
vuelva a crear repositorios anidados y deshaga el trabajo.

### 2026-08-12 — Copia de seguridad local

`c:\laragon\backups\actatec-db-20260812.zip` (7,4 MB, la base de datos) y
`c:\laragon\backups\tec-project-20260812.zip` (545,8 MB, el proyecto completo con las
imágenes). Pendiente subirlos a Drive.

### 2026-08-12 — Saneado de DigitalOcean

La factura pasó de 70,37 a unos 44 dólares al mes, unos 314 al año.

Se destruyó el servidor `thailivestream` (1 vCPU, 2 GB, Bangalore), que llevaba meses al
1,8% de uso por un proyecto abandonado y costaba 14,40 al mes con sus copias. Antes se le
hizo la foto `thailivestream-final-2026-08-12` (11,2 GB, 0,56 al mes), desde la cual se
puede resucitar la web entera si algún día se retoma. El sistema es Ubuntu 20.04, sin
soporte desde 2025, así que habría que actualizarlo antes de exponerlo a internet.

Se borraron seis de los siete snapshots del ERP, todos de entre julio de 2024 y agosto de
2025, incluido uno etiquetado `before-field-conversion`. Se conservó el más reciente,
`actafight.com-1755603370919` (38,68 GB). Todos quedaban superados por la copia local, que
además ya está en GitHub.

No hay direcciones IP reservadas huérfanas ni cortafuegos configurados.

### Estado actual de la infraestructura

| Qué | Dónde | Detalle |
| :-- | :-- | :-- |
| Servidor del ERP | DigitalOcean, `nyc3` | `actafight.com`, 2 vCPU / 4 GB / 120 GB, 32 USD/mes |
| ERP viejo publicado en | `tec.actafight.com` | Nunca se llegó a usar; solo datos de prueba |
| Dominio objetivo | `anvfightgear.com` | Namecheap, DNS propio de Namecheap (BasicDNS) |
| Correo del dominio | Google Workspace | Registros gestionados desde Mail Settings de Namecheap |
| Web pública | No existe | `www` apunta a la página de aparcamiento de Namecheap |
| Código | `github.com/daviddogu-code/ERP-ANV` | Privado, rama `main` |
| Copia local | `c:\laragon\www\tec` | Drupal 10.2.4, PHP 8.3.30, MySQL 8.4.3, 197 módulos activos |
