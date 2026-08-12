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

**Está ordenado de más urgente a menos.** Lo de arriba es lo que hay que mirar hoy; lo de
abajo puede esperar meses sin que pase nada. Si abres el archivo agobiado, quédate solo con
las dos primeras secciones y cierra.

Cuando una tarea se termina, se mueve a la sección "Hecho" con la fecha y una línea
explicando por qué se hizo. Esa explicación es lo que evita rehacer trabajo o deshacer
por error algo que estaba puesto a propósito.

---

## 1. Ahora mismo

- **Configurar el servidor de Singapur**, que ya existe y está vacío. Es la lista entera de
  la sección 3 y da para una tarde. No requiere decisiones del dueño salvo los tres puntos de
  correo y el de las cuentas de usuario.

## 2. Esta semana

- **Guardar los diez códigos de recuperación de Namecheap** fuera del móvil, en Drive o
  impresos. Namecheap no permite dos métodos de 2FA a la vez, así que si se pierde el
  teléfono esos códigos son la única forma de entrar en el dominio.

## 3. Antes de que entren los empleados

Lista de "no publicar sin esto". Casi todo es trabajo técnico durante el despliegue y no
requiere decisiones del dueño, salvo los tres puntos de correo y el de las cuentas.

- **Configurar el servidor**: Ubuntu, Apache, PHP, MySQL, cortafuegos y acceso solo por
  llave criptográfica.
- **Corregir los tres ajustes de `sites/default/settings.php`**, que hoy están puestos para
  desarrollo local y en un servidor público serían fallos reales:
  `rebuild_access` está en `TRUE`, lo que deja reconstruir la caché sin identificarse;
  los archivos privados viven en `sites/default/private`, dentro de la carpeta que sirve el
  servidor web, y deben estar fuera; y `trusted_host_patterns` solo admite `tec.test`, así
  que el sitio devolvería un error 400 a cualquier otro dominio.
- **Instalar el programa `patch`** en el servidor y comprobar después que el parche de
  `inline_entity_form` quedó aplicado. Sin ese programa Composer se lo salta en silencio.
- **Publicar en `erp.anvfightgear.com`** con un registro A en Namecheap → Advanced DNS y un
  certificado de Let's Encrypt. No hay que tocar `www`, ni el redireccionamiento, ni el
  correo del dominio.
- **Programar el cron.** Hoy Ultimate Cron reporta once tareas atrasadas.
- **Crear el buzón `erp@anvfightgear.com`** en Google Workspace, como alias que reenvía al
  dueño y no como cuenta de pago nueva. Es la dirección desde la que el ERP manda los
  correos desde el 2026-08-12.
- **Configurar el envío de correo a través del servidor de Google.** No es opcional:
  DigitalOcean bloquea el envío directo en las cuentas nuevas. El módulo `smtp` ya está
  instalado, solo hay que rellenar servidor, usuario y contraseña.
- **Activar DKIM y DMARC en `anvfightgear.com`.** Hoy solo existe SPF. DKIM se genera en el
  panel de Google Workspace y se pega en Namecheap → Advanced DNS; DMARC es un registro TXT
  escrito a mano.

  Estos tres puntos de correo van juntos: con uno o dos hechos, las recuperaciones de
  contraseña siguen sin llegar. Y mientras no funcionen, un empleado que olvide su clave
  depende de que alguien se la restablezca desde la línea de comandos.

- **Repasar las cuentas de usuario.** Hay seis y solo dos se han usado alguna vez. Hay que
  crear las de los tres empleados, revisar para qué sirven las otras y cambiar la contraseña
  del superusuario (`uid 1`), que tiene todos los permisos del sistema.

## 4. Cuando el ERP nuevo ya funcione

- **Dar de baja el servidor de Nueva York.** Ahí está el ahorro grande: 32 dólares al mes.
  Durante el solape se pagan los dos, pero el cobro es por horas. Conviene tenerlo encendido
  unos días por si acaso.
- **Borrar el snapshot `actafight.com-1755603370919`** (38,68 GB) cuando el ERP nuevo lleve
  un mes funcionando.
- **Borrar de Drive la carpeta `PRE-limpieza`** cuando exista la copia nueva y el servidor
  lleve unos días funcionando. Así deja de haber dos juegos de copias y desaparece el último
  sitio donde queda la clave de OpenAI del programador anterior.

## 5. Con fecha en el calendario

- **Antes del 3 de octubre de 2026** — confirmar en Namecheap (Profile → Billing) que la
  tarjeta guardada sigue vigente. Ese día renueva `anvfightgear.com`, que es el dominio sobre
  el que va a ir el ERP y la web pública. La renovación automática está activada, pero no
  sirve de nada si la tarjeta ha caducado.

## 6. Funcionalidad nueva

Sin prisa y sin orden fijo entre ellas. La lista de compra es la que más valor daría a corto
plazo.

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

## 7. Referencia: el plan del servidor de Singapur

Plan cerrado el 2026-08-12. El procedimiento técnico paso a paso vive en
`modules/custom/tec_production/README.md`, sección "Deployment procedure". Aquí están las
decisiones y el porqué de cada una, para no volver a discutirlas dentro de seis meses.

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

1. Crear la llave SSH, porque el paso siguiente la pide.
2. Crear el droplet en el panel. **Lo tiene que hacer el dueño**, son cinco minutos.
3. Dejarlo configurado: sistema, Apache, PHP, MySQL, `patch`, cortafuegos y accesos.
4. **Hacer copias nuevas ese mismo día.** Las que hay en Drive son del 12 de agosto por la
   mañana, anteriores a la limpieza de credenciales, y no valen como ingrediente. Antes de
   comprimir hay que comprobar que no ha reaparecido ninguna copia de `openai.settings.yml`
   con la clave dentro de `sites/default/files`: es la única carpeta que viaja en el zip y
   que GitHub no cubre, así que es el único sitio por donde la clave puede colarse.
5. Subir el ERP siguiendo el procedimiento del README: el código desde GitHub, la base de
   datos y los archivos desde esos zips nuevos, y `settings.php` escrito a mano.
6. Añadir el registro A de `erp.anvfightgear.com` en Namecheap.
7. Instalar el certificado con Let's Encrypt.
8. Probarlo unos días con el de Nueva York todavía encendido.
9. Destruir el de Nueva York.

Crear el servidor son veinte minutos. Dejarlo bien, una tarde.

### Lo que el ensayo no pudo probar

Se hizo sobre Windows y por HTTP, así que esto sigue sin verificar: HTTPS, la programación
del cron, el envío de correo, los permisos de las carpetas de archivos y el ajuste de
`opcache`.

## 8. Deuda técnica

Nada de esto corre prisa, pero conviene que esté escrito para que no se descubra por sorpresa.

- **Desinstalar del todo los cinco módulos de IA** (`ai`, `ai_interpolator`,
  `ai_interpolator_eca`, `ai_interpolator_openai`, `openai` y `openai_eca`). Hoy están
  instalados pero sin credenciales y con todos los interpoladores apagados, así que no hacen
  nada. Quitarlos del todo requiere planificarlo, porque `ai_interpolator` metió campos
  obligatorios en materiales, colores y unidades, y arrancarlos a lo bruto rompe cosas.
- **`views_aggregator` 2.1.1 dice que necesita Drupal 10.3 u 11** y el sitio corre 10.2.4,
  así que el informe de estado lo marca como incompatible. Hoy funciona, pero es uno de los
  módulos instalados a mano y conviene resolverlo antes de que sea un incidente.
- **`composer update` no se puede ejecutar en este proyecto.** Hay conflictos entre
  dependencias fijadas a Drupal 10.2.4 y otras que ya piden 10.3 u 11. Un intento llegó a
  desinstalar `drupal/inline_entity_form` por su cuenta. Hoy se sortea usando solo
  `composer install` y operaciones dirigidas. Algún día habrá que sanearlo.
- **Cuatro módulos viven fuera de Composer**: `pdf_serialization`, `views_entity_form_field`,
  `quicktabs` e `integer_to_decimal`. No están en `composer.json` ni en `composer.lock`, se
  descargaron a mano. Llegan al servidor solo a través del repositorio. Dos de ellos llevan
  parches aplicados a mano (ver "Hecho").
- **Estudiar `composer-exit-on-patch-failure`** en `composer.json`, para que un parche que no
  se aplica corte la instalación en voz alta en lugar de pasar desapercibido.
- **El editor guarda a veces en UTF-16 en vez de UTF-8**, y eso rompe cualquier fichero PHP,
  CSS, JS o Markdown que toque. Ha pasado ya media docena de veces. De momento se detecta y
  se corrige a mano después de cada edición, lo que duplica el coste de tocar un archivo.

  Investigado el 12 de agosto: **no es un problema de configuración de este proyecto, sino un
  fallo conocido de Cursor en Windows**, confirmado por un empleado suyo el 21 de julio de
  2026 en el foro oficial. Falla el motor que escribe los ficheros en disco; ni el editor ni
  la consola tienen nada que ver. Sigue sin corregir: nos ocurrió hoy con la versión 3.15.6,
  tres semanas después de que lo reconocieran, y no han dado fecha.
  Informe: https://forum.cursor.com/t/agent-write-tool-creates-new-files-as-bom-less-utf-16le-on-windows-breaking-c-compilation/166312

  Cosas que **no** funcionan, ya probadas por otros usuarios: poner `files.encoding` en los
  ajustes, el `charset = utf-8` del `.editorconfig` que ya trae el proyecto, y cerrar y
  reabrir el fichero. El agente se los salta todos. Lo único que funciona, y es lo que
  recomienda el propio Cursor, es escribir los ficheros desde la consola indicando la
  codificación a mano.

  Para saber si lo arreglan, el registro de novedades de Cursor no sirve, porque solo anuncia
  funciones nuevas y esto no aparecería. Hay dos vías: seguir el hilo del foro, donde
  prometieron avisar, o la prueba directa después de cada actualización, que es escribir un
  fichero y mirar sus bytes.

  Pendiente de decidir: montar un *hook* de Cursor del tipo `afterFileEdit` que detecte el
  UTF-16 y lo convierta solo. No arregla el fallo, pero lo vuelve indoloro y protege del caso
  grave, que es un `.php` mal guardado tumbando el ERP sin motivo aparente.
- **Limpiar carpetas sobrantes** dentro del proyecto:
  `config/sync.pre-restore-20260810023747` (1.248 archivos) y
  `modules/custom.pre-restore-20260810023746` (134 archivos), restos de una restauración del
  10 de agosto que ya está superada por las copias del 12. Están excluidas del repositorio,
  así que no llegan a GitHub, pero sí ocupan sitio y confunden las búsquedas.
- **Borrar `c:\laragon\backups\nested-git-20260812`** dentro de unas semanas, cuando esté
  claro que la conversión de los módulos no dio problemas.
- **Borrar `c:\laragon\backups\git-history-20260812`** (119 MB, el historial de Git anterior
  a la reescritura) cuando el repositorio nuevo lleve unas semanas funcionando. Ojo: esa
  carpeta **sí contiene** la clave de OpenAI en sus objetos antiguos, así que no debe salir
  del ordenador ni subirse a ningún sitio.
- **Borrar el snapshot `thailivestream-final-2026-08-12`** dentro de tres meses si no se
  retoma ese proyecto. Cuesta 0,56 dólares al mes y es lo único que queda de esa web.
- **Mirar las versiones móviles de GitHub y Cursor.** GitHub tiene aplicación de iOS y
  Android, con la que se puede leer y editar este backlog desde el teléfono. Cursor tiene
  agentes en la nube que se lanzan desde el navegador del móvil.

---

## Hecho

### 2026-08-12 — El servidor de Singapur, creado

Existe y responde. Se llama `erp-anv-sgp1`, su dirección pública es **188.166.255.1**, corre
Ubuntu 24.04.4 LTS con 2 GB de memoria, 1 CPU y 50 GB de disco, y cuesta 15,60 al mes con las
copias diarias incluidas, que se hacen de 3 a 7 de la madrugada hora de Tailandia.

Se entra con llave SSH, sin contraseñas. La llave se creó ese mismo día en el PC del dueño
(`C:\Users\Acer\.ssh\id_ed25519`, tipo ed25519, sin frase de paso para que los despliegues no
pidan teclear nada). La mitad privada no tiene copia todavía: **si se pierde ese archivo se
pierde el acceso al servidor**, y recuperarlo obliga a pasar por el panel de DigitalOcean.
Hay que resolverlo durante la configuración.

La razón de todo el traslado quedó medida: desde el PC de la fábrica, el servidor de Singapur
responde en **33 milisegundos**, frente a los 250 del de Nueva York. Siete veces más rápido,
que es la diferencia entre un ERP que va suelto y uno que va a tirones.

Un detalle para la configuración: la máquina no tiene memoria de intercambio (*swap*)
configurada. Con 2 GB reales conviene añadirla, porque `composer install` necesita cerca de
1 GB él solo y, si se queda corto, Linux mata el proceso a media faena sin explicar por qué.

### 2026-08-12 — Las copias, fuera del disco duro

Los dos zips ya están en Google Drive, en `Backups ERP-ANV / 2026-08-12`, con el mismo tamaño
que en el disco (7,4 MB la base de datos y 545,8 MB el proyecto), lo que confirma que la
subida terminó entera. Con esto el ERP deja de depender de un solo disco, que era el punto más
frágil que había.

Con una pega, detectada después: se subieron por la mañana, antes de la limpieza de
credenciales de la tarde, así que llevan dentro la clave de OpenAI del programador anterior.
No es un problema de seguridad, porque Drive es privado y la clave no es nuestra, pero sí de
etiquetado: el procedimiento de despliegue usa esos zips como ingrediente, y usar estos
resucitaría la clave en el servidor nuevo. De ahí las tres tareas repartidas por la lista,
renombrar la carpeta, hacer copias nuevas el día del despliegue y borrar las viejas después.

Recuperar desde estos zips tampoco sería un desastre, porque la limpieza sí está en GitHub: se
restaura el zip y se importa encima la configuración limpia del repositorio. Las dos copias se
complementan.

### 2026-08-12 — Limpieza de todo lo que quedaba del programador anterior

El ERP guardaba una clave de OpenAI en texto plano, en `config/sync/openai.settings.yml`,
apuntando a la cuenta personal del programador que montó el sistema original. Era la cuenta
que había que ir recargando con dinero. Seis procesos automáticos llamados *"TEC Inventory
term: Calculate units"* preguntaban a GPT las conversiones de unidades **cada vez que se
creaba o modificaba un material**; con más de ochocientos materiales, ahí estaba el gasto.
Esos seis ya estaban desactivados desde hacía tiempo, pero cuatro campos seguían armados:
tres de las unidades de medida y el de color.

Se hizo lo siguiente: vaciar la clave y el identificador de organización, apagar esos cuatro
campos, bloquear su cuenta de usuario `uid 3` (activa, con rol de dirección y sin usarse
desde julio de 2024) y cambiar el correo del sitio, que era el suyo, por
`erp@anvfightgear.com`.

Al apagar el último interpolador, el propio módulo retiró tres campos suyos que ya no hacían
falta. Eso es normal, no es un daño.

Una auditoría completa confirmó que no queda nada más: su dominio aparecía en un solo sitio
del código, la clave era la única credencial de los 1.255 objetos de configuración, los
repositorios externos de Composer apuntan solo a Drupal y GitHub, el remoto de Git es el
propio, y la configuración de SMTP estaba vacía. Sí había **cuatro copias del fichero de la
clave** en el disco; dos estaban obsoletas y se vaciaron también, y una de ellas vivía dentro
de `sites/default/files`, o sea que habría viajado al servidor de Singapur dentro del zip.

Queda un frente que no se puede auditar desde aquí: el servidor de Nueva York, que montó él y
donde probablemente sigue instalada su llave de acceso. Se resuelve solo, porque el servidor
nuevo se crea desde cero y el viejo se destruye.

### 2026-08-12 — El historial de Git, reescrito para purgar la clave

La clave estaba en 28 de los 29 commits del repositorio, y se había subido a GitHub esa misma
mañana. Se descartó empezar de cero, porque el historial documenta el rediseño del
inventario, la ampliación del CRM y el módulo de stock control, y esos mensajes valen. En su
lugar se reescribieron los 30 commits eliminando ese único fichero, y luego se devolvió con
los campos vacíos en el último.

Se comprobó de dos maneras. Buscando la clave en los 30 commits: cero apariciones. Y
comparando el inventario completo de archivos antes y después: de 17.457 entradas, la única
diferencia es la desaparición de ese fichero. Los otros 17.456 son idénticos hash a hash, así
que la reescritura no tocó nada más.

El historial anterior está íntegro en `c:\laragon\backups\git-history-20260812`.

El repositorio de GitHub se borró y se recreó vacío, en vez de forzar la subida, porque
forzándola los commits viejos habrían seguido accesibles en los servidores de GitHub durante
un tiempo. Sobre el repositorio nuevo se subieron los 31 commits ya limpios.

La comprobación no se hizo mirando la copia local, sino descargando de GitHub una copia
independiente y revisándola commit a commit: los 31 están limpios y el fichero de la clave
llega con los dos campos vacíos.

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
completo, unos 17.456 archivos. La identidad de Git (`daviddogu-code` con el correo oculto de
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
| Correo del ERP | `erp@anvfightgear.com` | Declarado, pero el buzón aún no existe y el envío no está configurado |
