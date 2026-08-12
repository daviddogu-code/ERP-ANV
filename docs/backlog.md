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

- **Ensayo de despliegue en local.** Clonar el repositorio en una carpeta nueva, ejecutar
  `composer install` y restaurar los dos zips, para comprobar que esos tres ingredientes
  bastan para levantar el ERP entero. Se lanzó el 2026-08-12 en un chat aparte.
  Hasta que no salga limpio no se toca el servidor de producción.

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
  configurado. Son gratuitos. Hay que dejarlo puesto antes de subir datos reales.

## Despliegue a DigitalOcean

- **Crear el servidor en Singapur (`sgp1`).** El actual está en Nueva York (`nyc3`), lo que
  añade unos 250 ms a cada petición desde Tailandia y explica buena parte de la lentitud
  del ERP viejo. Mismo tamaño, mismo precio.
- **Escribir el procedimiento de despliegue** en `modules/custom/tec_production/README.md`
  una vez el ensayo confirme que funciona. Cuatro pasos: clonar, `composer install`,
  restaurar base de datos, restaurar `sites/default/files`.
- **Publicar el ERP en `erp.anvfightgear.com`.** Es un único registro A nuevo en
  Namecheap → Advanced DNS apuntando a la IP del servidor. No hay que tocar `www`, ni el
  redireccionamiento, ni nada del correo.
- **Dar de baja el servidor de Nueva York** cuando el nuevo lleve funcionando y el dominio
  ya apunte ahí. Durante el solape se pagan los dos, pero el cobro es por horas.
- **Revisar la periodicidad de las copias.** Hoy son semanales. Para un ERP en uso real
  probablemente convenga diarias.

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

---

## Hecho

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
