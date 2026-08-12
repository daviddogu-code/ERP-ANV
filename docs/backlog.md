# Backlog — ERP ANV

> Registro vivo de tareas pendientes y de decisiones ya tomadas.
> Escrito en español porque el lector principal es el dueño del proyecto.
> Las otras notas de `docs/` están en inglés y son documentación técnica; esta no.
>
> Última actualización: 2026-08-13.

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

- **Decidir si se sube el núcleo a 10.6.15.** Es lo único que hay abierto en la actualización.
  Las fases 0 y 1 están cerradas, el ensayo dice que resuelve sin conflictos, y hay dos razones
  para hacerlo que van más allá de la seguridad: la Fase 1 **no se puede terminar sin ello**
  —`views_aggregator` exige 10.3 y corremos 10.2.4— y de paso **arregla el error 500 de la
  página de pedidos**. Ver el apartado 8.

~~**Confirmar en Git el trabajo del 12 y el 13 de agosto.**~~ Hecho el 13 de agosto en tres
commits: la limpieza de módulos, la red de seguridad de la Fase 0 y el saneado de Composer.

El ERP ya está publicado en `https://erp.anvfightgear.com` desde el 12 de agosto.

**La revisión de Lukpla queda aparcada** por decisión del dueño el 13 de agosto: se le dirá que
de momento no entre, para poder trabajar sin la restricción de no romperle nada. Lo que hay
preparado para cuando se retome:

- ~~**Crear la cuenta de Lukpla.**~~ Hecha, con rol *supervisor*, directamente en el servidor.
- **Llevar al servidor la limpieza de módulos del 13 de agosto**, con un cuidado que antes no
  hacía falta. **Local y servidor ya no son la misma cosa**: la cuenta de Lukpla existe allí y
  no aquí, y si ella ha empezado a revisar puede haber tocado vistas o pantallas. Los usuarios
  son contenido y no corren peligro, pero **la configuración sí**: un `drush cim` desde local
  machacaría cualquier cambio que ella haya hecho. Antes de desplegar hay que ejecutar
  `drush config:status` **en el servidor** y mirar si ha divergido. Si ha divergido, primero se
  traen sus cambios, y luego se sube la limpieza.

  Y una comprobación más, por lo que pasó al quitar los módulos de IA: **contar los procesos de
  ECA en el servidor antes y después** de desplegar. Deben ser 36. Al desinstalar en local,
  Drupal borró nueve procesos en cascada sin avisar, y la cuenta de módulos salía bien igual.
  `drush sql:query "SELECT COUNT(*) FROM config WHERE name LIKE 'eca.eca.%'"`.
- **Preparar dónde apunta lo que vea**, aunque sea una hoja de cálculo compartida. Por chat
  se evapora en tres días.
- **Darle un encargo concreto**, empezando por compras, en vez de un "míralo a ver qué te
  parece".
- **Confirmar si se maneja en inglés**, que es el idioma en el que está el ERP entero.

## 2. Esta semana

- **Guardar los diez códigos de recuperación de Namecheap** fuera del móvil, en Drive o
  impresos. Namecheap no permite dos métodos de 2FA a la vez, así que si se pierde el
  teléfono esos códigos son la única forma de entrar en el dominio.

## 3. Antes de que entren los empleados

Esto va en dos fases, decidido el 12 de agosto. **Primero entra solo Lukpla, y no a trabajar
sino a revisar**: tiene que ver el ERP entero y proponer cambios antes de que se empiece a usar,
porque hay partes del negocio, compras sobre todo, cuya lógica conoce ella y no el dueño. Ese es
el motivo de querer publicarlo cuanto antes. Después, ya con sus cambios hechos, entran los tres
empleados a usarlo de verdad.

**La primera fase se completó el 12 de agosto**: servidor configurado, los tres ajustes de
`settings.php` corregidos, parche verificado, publicado en `erp.anvfightgear.com` con
certificado. El detalle está abajo, en Hecho. Lo que falta para que Lukpla empiece está en la
sección 1.

El resto de esta lista es el "no publicar sin esto" de la segunda fase, la de los tres
empleados. Casi todo es trabajo técnico y no requiere decisiones del dueño, salvo los tres
puntos de correo y el de las cuentas.

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

- **Repasar las cuentas de usuario.** Hay seis, ninguna usada desde 2024 y todas bloqueadas
  salvo una. Además de crear las de los tres empleados, el repaso del 12 de agosto dejó tres
  cosas concretas:

  - ~~**El superusuario (`uid 1`) se llama `drusphere`**~~ — hecho el 12 de agosto. Se
    renombró a `david` y se le puso contraseña nueva. Para liberar ese nombre hubo que
    renombrar antes la cuenta bloqueada `David` (`uid 4`) a `david-antiguo`.
  - **`devT` (`uid 7`) tiene rol de administrador**, es decir permisos totales, con un correo
    en un dominio que parece una errata del suyo (`drupshere.com`). Está bloqueada, así que no
    es urgente, pero es una segunda llave maestra que no es del dueño: lo suyo es borrarla, no
    dejarla bloqueada.
  - **Cuentas muertas por revisar y probablemente borrar**: `manager`, `executive` (la de
    Oscar, bloqueada el 12 de agosto), `rat` con correo en `actafight.com`, y una llamada
    `David` que es del dueño pero está bloqueada.

### Limpieza de datos y arreglo del importador

**Todo el contenido que hay dentro es de prueba y se borra entero.** Decisión del dueño del 12
de agosto, y anula cualquier duda anterior sobre qué se rescataba: el ERP nunca se usó, así que
no hay nada dentro que valga la pena conservar. Se vacía y se empieza con datos reales.

Lo que se borra, con el recuento del 12 de agosto:

- **110 órdenes de compra** con 522 líneas, y **13 pedidos de venta** con 61 líneas.
- **869 materiales**, con los 13.615 movimientos de inventario que cuelgan de ellos.
- **13 productos**, con sus 83 variaciones de color y 118 de talla.
- **22 fichas de cliente** y **12 registros de producción.**

Lo que se queda, porque son listas de configuración y no datos: **tipos de producto (23),
unidades (13), tallas (14), tipos de material (23) y colores (37)**. Son las listas de las que
tiran luego los materiales y los productos de verdad, y están bien. La revisión pendiente de
los colores deja de ser delicada: lo que la hacía arriesgada era que 300 materiales colgaban de
esa paleta, y esos materiales se van.

Dos vocabularios sí se van, decidido antes y sin cambios:

- **Patrones (11)**: un trozo del ERP que quedó a medias y que ningún producto usa. Al
  quitarlos hay que retirar también la estructura vacía que dejan atrás: el campo en productos,
  sus dos vistas y los campos propios del vocabulario.
- **Marcas (12)**: se van con los productos de prueba que las usaban.

Tres avisos para cuando se ejecute. El borrado **tiene un orden obligado**, porque unas cosas
cuelgan de otras: primero movimientos de inventario y líneas, después pedidos, productos y
materiales, y al final los vocabularios. Y son casi 15.000 registros, así que va en un script,
no a mano desde la pantalla. Queda pendiente de montar, aparte, el acceso directo desde la
portada para las tallas, como el que ya tienen tipos de producto y unidades.

El tercer aviso es nuevo, del 13 de agosto, y es el más peligroso porque falla en silencio.
**El vocabulario de tipos de contacto no se toca bajo ningún concepto.** El módulo
`tec_crm_ux` lleva grabados a fuego los dos identificadores en su código:

    const TEC_CRM_UX_TYPE_CUSTOMER = 1395;
    const TEC_CRM_UX_TYPE_SUPPLIER = 1396;

De esos dos números depende que el ERP sepa si una ficha es de un cliente o de un proveedor, y
eso decide qué campos se le enseñan a cada uno. Si esos términos se borran y se vuelven a
crear, los nuevos tendrán otros identificadores y el sistema dejará de distinguirlos **sin dar
ningún error**: empezará a enseñar campos de proveedor en las fichas de cliente. Si alguna vez
hay que recrearlos, hay que actualizar esas dos líneas de
`modules/custom/tec_crm_ux/tec_crm_ux.module` a la vez.

**El importador de materiales** pasa a ser la pieza central del arranque: con el borrado ya no
sirve para corregir lo que hay, sino para meter el catálogo real de golpe. Existe
(`tec_inventory_csv_importer`) y por él entraron 470 de los 869 materiales de prueba, pero está
a medio hacer:

- **Rellena 5 de los 56 campos** de un material: nombre, descripción, trazabilidad, unidad de
  uso y tipo de material. Precios, unidades de compra y de stock, factores de conversión,
  punto de pedido, plazo de entrega, imagen, SKU y embalaje entran vacíos. De ahí la
  sensación de que "la información no está bien": no es incorrecta, es que falta.
- **El proveedor no se enlaza nunca.** El importador sí lee una columna `Suppliers` del CSV,
  está declarada ahí dentro, pero no está conectada a ningún campo: lee el dato y lo tira. Lo
  mismo le ocurre a una columna de coste.
- **Hay dos campos de proveedor** en los materiales y hay que elegir cuál es el bueno antes de
  tocar nada: `field_tec_vendor` (etiqueta "Supplier", obligatorio, se elige de una lista) y
  `field_tec_suppliers` (etiqueta "Suppliers", opcional, admite varios y crea proveedores
  nuevos sobre la marcha).
- **Procesa solo 100 filas por pasada**, así que con 869 materiales hay que lanzarlo nueve
  veces o subir ese límite.
- **Inventa unidades y tipos de material** cuando el texto del Excel no coincide exactamente.
  Escribir "metros" donde el sistema tiene "metro" no da error: crea una unidad nueva. Como
  las 13 unidades y los 23 tipos son correctos, conviene apagarlo para que una errata falle
  en voz alta en lugar de ensuciar el catálogo.
- Arrastra además una docena de columnas declaradas y sin usar, restos de pruebas, que
  enredan a la hora de entender qué espera el fichero.

El trabajo es: elegir el campo de proveedor, mapear los campos que faltan, limpiar las columnas
sobrantes y generar la plantilla de Excel con una columna por dato y los nombres exactos que el
importador espera.

**Se arregla antes del borrado, no después.** Así el ERP no se queda ni un solo día vacío y sin
manera de rellenarlo. Y de paso la plantilla se puede ensayar sobre los materiales de prueba,
que para eso siguen ahí: el importador reconoce cada material por su nombre y actualiza los que
ya existen, así que se ve enseguida si los 56 campos entran donde deben.

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
- **Web pública de `anvfightgear.com`**, de presentación de la fábrica OEM. **La estructura, el
  argumentario y el análisis de la competencia ya están escritos en
  [`docs/web-anvfightgear.md`](web-anvfightgear.md)**, listos para pasárselos a otra ventana de
  agente. Lo único que bloquea el trabajo son las fotos y el vídeo del taller, que solo puede
  hacer el dueño. Corrección del 12 de agosto: el dominio no muestra una página de
  aparcamiento, simplemente **no tiene ningún registro A**, ni él ni `www`. No hay nada roto
  que arreglar.
- **Portal de clientes**: que entren con su cuenta, vean sus productos y hagan sus propios
  pedidos. Los requisitos completos están escritos en **`docs/customer-portal.md`**: el
  recorrido del cliente, lo que no puede ver, los estados que se le enseñan y las decisiones
  que faltan por cerrar. **Este sí depende del ERP** y no debe empezarse hasta que
  el modelo de datos esté asentado, después de la limpieza y de los cambios que proponga
  Lukpla. Dos avisos de ese documento que conviene no perder de vista: falta el enlace entre
  la cuenta de usuario y la empresa del cliente, sin el cual no hay portal posible, y el rol
  Customer **hay que vaciarlo y rehacerlo** antes de crear la primera cuenta, porque hoy
  permite crear productos y meter movimientos de inventario.
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

## 8. Subir a Drupal 11

Auditado el 13 de agosto por cuatro revisiones en paralelo. El sitio corre **Drupal 10.2.4, que
ya no tiene soporte**.

Ese mismo día, un diagnóstico con las herramientas de Composer añadió dos datos que cambian la
urgencia. El primero: `composer audit` encuentra **82 avisos de seguridad conocidos en 10
paquetes**, y 42 de ellos son del propio núcleo de Drupal, con varios de gravedad *crítica*. Son
dos años y medio de parches sin aplicar. Esto ya no es una tarea de "antes de que cumpla un
año": es lo más urgente de la lista técnica en cuanto el ERP tenga datos reales dentro.

El segundo dato es la buena noticia, y es grande: **la subida del núcleo resuelve limpia**. Ver
el punto 3.

**No se puede saltar de 10.2.4 a Drupal 11 directamente.** Hay que pasar antes por 10.3 o
superior, porque Drupal 11 borró todos los guiones de actualización anteriores a esa versión. Es
una puerta obligatoria, no una recomendación. PHP 8.3, que es lo que ya corremos, sirve.

### El orden

1. **Meter en Composer los módulos sueltos y reconciliar las versiones.** *Hecho a medias el 13
   de agosto: `composer.json` ya describe la realidad —ver la Fase 1 en el apartado de Hecho—,
   pero **el `lock` no se puede sincronizar hasta subir el núcleo**, porque `views_aggregator`
   2.1.1 exige `^10.3 || ^11` y corremos 10.2.4. En la práctica esto funde este punto con el 3.*

   Los sueltos eran
   dieciocho de los activos, y los descuadrados **no son cinco sino veinticuatro**, medidos el 13
   de agosto comparando uno a uno el disco contra el `lock`. Entre ellos `ds` (3.22 en disco
   frente a 3.19.0 en el `lock`), `token`, `gin_login`, `jquery_ui`, `better_exposed_filters`,
   `smtp`, `feeds` y `ief_table_view_mode`, este último con una rama de desarrollo en disco
   donde el `lock` pide una versión estable.

   **Por qué Composer no lo detecta**, que es lo que hacía invisible el problema: Composer no
   mira los ficheros de los módulos, se fía de su propia contabilidad en
   `vendor/composer/installed.json`. Ahí sigue apuntado `ds` 3.19.0 aunque en disco haya un 3.22.
   Por eso `composer install --dry-run` dice tan tranquilo "nada que hacer": para él todo cuadra.
   Alguien actualizó esos módulos copiando ficheros por encima y la contabilidad se quedó atrás.

   **Cuándo estalla.** Hoy no, mientras la carpeta `vendor` siga viva. Pero `vendor` está
   excluida del repositorio y `modules/contrib` no, así que en una reconstrucción del servidor
   desde cero —o si alguien borra `vendor` y lanza `composer install`— Composer escribiría las
   versiones del `lock` encima de las del disco. Y hay una consecuencia peor: **volvería a
   instalar en disco todo lo que quitamos**, incluidos `ai_interpolator`, `ai_interpolator_openai`,
   `openai`, los diez paquetes de Commerce, `shield` y `quicklink`. Desinstalados en la base de
   datos seguirían, pero el código volvería.
2. **Resolver los dos bloqueantes que quedan.** Tras la limpieza del 13 de agosto solo quedan
   dos módulos activos sin ninguna versión para Drupal 11:
   - `pdf_serialization`, que genera los PDF de las órdenes de compra. Está sin soporte desde
     2023. El sustituto es Entity Print, pero no es un recambio directo: cambia la forma de
     generar el documento.
   - `simple_popup_views`, sin sucesor oficial y sin actividad desde noviembre de 2022. Hay que
     inventariar qué vistas lo usan (superbom, productos, líneas de pedido y patrones) antes de
     decidir con qué se sustituye.
3. **Subir el núcleo, que resuelve limpio y llega más lejos de lo que pensábamos.** Simulado el
   13 de agosto con `composer update drupal/core-recommended --with-all-dependencies --dry-run`:
   **no da ni un conflicto**, y no se queda en la 10.3 sino que llega a la **10.6.15**, la última
   de la rama 10. Son 22 actualizaciones y 3 retiradas, y **no toca ni un solo módulo contribuido**:
   solo el núcleo y sus dependencias internas (Twig, Guzzle, los polyfills de Symfony).

   Esto tumba la idea de que "en este proyecto no se puede actualizar". No se puede lanzar un
   `composer update` a pelo, que es otra cosa; una actualización dirigida del núcleo sí.

   Dos avisos para no confiarse. Que Composer resuelva **no significa que el sitio funcione**:
   eso lo dicen los módulos y el código, y hay que pasar antes el informe de `upgrade_status` y
   probar a fondo. Y conviene hacerlo **después** del punto 1, porque si no se reconcilian antes
   las versiones, la actualización arrastra los veinticuatro descuadres.

   A cambio se llevan por delante la mayoría de los 82 avisos de seguridad, porque el núcleo,
   Twig, Guzzle y los polyfills están todos en la lista de lo que sube. Quedarían pendientes
   solo cuatro paquetes: `eca`, `entity_browser`, `mail_login` y `psy/psysh`. Y aquí
   `views_aggregator` deja de dar problemas solo.
4. **ECA, de la rama 1 a la 2**, con sus submódulos y con `bpmn_io` de la 1 a la 2. Es un salto
   de versión mayor y mueve las transacciones de inventario, así que es la parte que más hay
   que probar. Importante: **no ir a ECA 3**, que exige Drupal 11.2 o más.
5. **Los temas, que son la parte cara.** `dxpr_theme` va de la versión 5 a la 8, y su rama
   actual lleva congelada desde enero de 2024. Encima declara que necesita `color`, que
   desapareció del núcleo, y sobrescribe Modernizr y Classy, que tampoco existen ya. Su tema
   base, `bootstrap5`, va de la 3 a la 4. Y Gin, el de administración, de la 3 a la 4.
6. **El resto de módulos**, empezando por `field_permissions`, cuya versión instalada
   **prohíbe expresamente** Drupal 11, y por los que cambian de versión mayor: `select2`,
   `field_group`, Better Exposed Filters, `flag`, `mimemail`, `module_filter`, `mail_login`,
   `quicklink`, `coffee`, `flood_control`, `views_bulk_edit` y `xls_serialization`.
7. **Entonces sí**, `drupal/core-recommended` a la 11 y Drush de la 12 a la 13.

### Lo nuestro está bien

El código propio **no es el problema**, y esa fue la sorpresa agradable de la auditoría. Los
cuatro módulos nuestros no usan ni una sola de las funciones que Drupal 11 elimina. Todo lo que
hace falta son cinco líneas: el `core_version_requirement` de `tec_crm_ux`, `tec_inventory`,
`admin_form_styles`, `tec_brands` y `tec_crm`, que dicen `^10` y tienen que decir `^10 || ^11`.
`tec_production`, que es el módulo más grande, ya está listo. Una o dos horas contando pruebas.

Quedan además tres cosas menores que funcionan en Drupal 11 pero desaparecerán en el 12: dos
anotaciones de plugin que habrá que pasar a atributos de PHP 8, un `{% import _self %}` en una
plantilla, y un parámetro que PHP 8.4 quiere ver escrito como `?FieldItemListInterface`.

### `minimum-stability: dev`

Sigue puesto en `composer.json`, y no es teórico: hay diez paquetes corriendo sobre ramas de
desarrollo en lugar de versiones publicadas, entre ellos ECA, `eck`, `bpmn_io`, `mimemail` y
`ultimate_cron`. Eso hace que la instalación no sea reproducible y que pueda colarse código sin
aviso de seguridad. Cuando toque tocar Composer, hay que pasarlo a `stable` y marcar la
excepción solo en los que de verdad no tengan versión estable.

### Dos carpetas de más

En `modules/custom` hay **seis** carpetas y solo cuatro módulos activos. `tec_brands` y
`tec_crm` están en disco, sin código PHP, solo con configuración, y desinstalados desde hace
tiempo. Se pueden borrar del repositorio.

## 9. Deuda técnica

Nada de esto corre prisa, pero conviene que esté escrito para que no se descubra por sorpresa.

- **Borrar un material en uso avería en silencio las líneas que lo usan.** Descubierto el 13 de
  agosto durante la prueba funcional. Cuando una línea de pedido tiene en su escandallo un
  material que ya no existe, el proceso de ECA que calcula el total arranca, se para en seco al
  intentar cargar ese material y **deja puesta la bandera de bloqueo**. No da error ni aviso: el
  total simplemente deja de actualizarse, y como la bandera se queda puesta, esa línea no
  vuelve a recalcular nunca más aunque se arregle el material. Hoy hay cinco líneas así (1567,
  4076, 4077, 4078 y 4079) y 2.459 referencias rotas en total, apuntando a 88 materiales
  borrados. **El borrado de datos de prueba se lleva por delante esas referencias, pero no el
  fallo**, que volverá a aparecer el día que alguien borre un material que esté en uso con
  datos reales. Hacen falta dos cosas: impedir el borrado de un material referenciado (o
  avisar), y que el proceso, si no encuentra el material, se salte ese elemento y siga en vez de
  morirse. Mientras tanto, la señal para detectarlo es que queden banderas `%lock%` puestas:
  `SELECT flag_id, COUNT(*) FROM flagging WHERE flag_id LIKE '%lock%' GROUP BY flag_id`. En
  reposo solo debe salir `tec_eca_gui_lock` con una.
- **Una errata mete "emergencias" falsas en el registro.** El proceso `process_rxuimsq` ("TEC
  Inventory: Calculation data") escribe su mensaje de arranque con nivel **0, que es
  *Emergencia*, el más grave que existe**, mientras que el de fin lo escribe con nivel 7, que es
  *depuración*. Es evidente que se tecleó un `0` donde iba un `7`. El mensaje en sí es
  inofensivo, dice solo "Running: ...", pero entra una emergencia falsa en el registro cada vez
  que se guarda un inventario. Ensucia el registro y engañaría a cualquier alerta que se monte
  el día de mañana. Se arregla cambiando un carácter en
  `config/sync/eca.eca.process_rxuimsq.yml`, línea 166. No se ha tocado para no meter cambios de
  configuración no pedidos justo antes del despliegue al servidor.
- **`views_aggregator` no es lo que parecía.** El informe de estado lo marca como incompatible
  y de ahí salió la idea de que era un problema. Es al revés: la versión instalada, la 2.1.1,
  ya vale para Drupal 10.3 y 11, y falla porque es **demasiado nueva** para el 10.2.4 que
  corremos. Subir de versión no lo rompe, lo arregla. Se usa en una sola vista, el resumen de
  cálculo de materiales de un pedido, y ahí tiene un fallo real: cuando un campo viene vacío,
  la página del pedido devuelve un error 500. Comprobado el 13 de agosto en el pedido 348, que
  es de los antiguos; los ocho pedidos siguientes cargan bien. Se resuelve solo al subir a
  Drupal 10.3.
- **`composer update` a pelo no se puede ejecutar; dirigido sí.** Matizado el 13 de agosto. Lo
  que no se puede es lanzar un `composer update` sin argumentos: un intento llegó a desinstalar
  `drupal/inline_entity_form` por su cuenta. Pero una actualización dirigida del núcleo sí
  resuelve limpia (ver el apartado 8), así que el proyecto no está tan bloqueado como creíamos.
  Lo que sigue pendiente es sanear el fichero para que un `update` general deje de ser peligroso.
- **`composer.json` pide 38 paquetes que no están instalados.** Toda la suite de Commerce (diez
  paquetes), `search_api`, `features`, `memcache`, `symfony_mailer`, `taxonomy_manager`, `shs`,
  `image_effects`, `geofield_map`, y los que desinstalamos nosotros: `shield`, `quicklink`,
  `eca_vbo` y `ai_interpolator_openai`. No hacen daño estando ahí, pero **participan en el
  cálculo de dependencias** y de ahí sale buena parte de los conflictos. Quitarlos es lo que más
  simplifica el problema: de 15 comodines `*` y 11 ramas de desarrollo se pasa a 4 y 7.
- **Dos módulos críticos entran por la puerta de atrás.** `inline_entity_form`, que además
  **lleva un parche nuestro**, no aparece en `require`: llega como dependencia de
  `ief_table_view_mode`. Y `flag`, del que depende todo el mecanismo de bloqueo de ECA, llega a
  través de `eca_flag`. Esto explica el incidente en que un `composer update` desinstaló
  `inline_entity_form`: nada se lo impedía. Hay que anclarlos explícitamente, y hacerlo **antes**
  de borrar los 38 muertos, porque dos de ellos (`ief_popup` e `ief_complex_open`) son los que
  arrastran `inline_entity_form`.
- **El `merge-plugin` apunta a un fichero que no existe**:
  `modules/contrib/charts/modules/charts_billboard/composer.json`. Esa carpeta no está en el
  proyecto. Es una mina enterrada en la configuración de Composer.
- **No son cuatro los módulos que viven fuera de Composer, son veintitrés.** Corregido el 13 de
  agosto; lo que decía antes esta línea se quedaba muy corto. Los que están activos y no
  aparecen en `composer.lock` son: `ai_interpolator_eca`, `base_field_override_ui`,
  `csv_serialization`, `draggableviews`, `entity_browser_enhanced`, `entity_browser_vertical`,
  `entity_reference_modal`, `epp`, `field_label`, `file_uploader`, `file_uploader_uppy`,
  `float_labels`, `markup`, `pdf_serialization`, `quicktabs`, `simple_popup_views`, `verf`,
  `views_aggregator`, `views_conditional`, `views_data_export`, `views_entity_form_field` y
  `xls_serialization`. Llegan al servidor solo a través del repositorio. Dos llevan parches
  hechos a mano (ver "Hecho"). Hay que meterlos en Composer **antes** de intentar cualquier
  actualización, porque mientras estén fuera cualquier `composer update` los pisa o los ignora.
- **En cinco módulos el disco va por delante de `composer.lock`**: `ds`, `gin_login`, `shield`,
  `smtp` y `feeds`. Alguien los actualizó copiando ficheros en vez de con Composer. Esto
  importa hoy y no dentro de seis meses, porque el procedimiento de despliegue ejecuta
  `composer install` en el servidor y ese comando instala la versión que dice el `lock`, no la
  del disco. O el servidor está corriendo versiones más viejas que local sin que lo sepamos, o
  el próximo despliegue las degrada en silencio. **Hay que comprobarlo.**
- **El `composer.lock` está incoherente**: el núcleo figura en 10.2.4 pero una de sus piezas,
  `core-composer-scaffold`, en 10.6.15. Es el rastro de una actualización que se quedó a medias
  y probablemente parte de la causa de que `composer update` no funcione.
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
- **Queda el código de la IA en el disco**, aunque los módulos estén desinstalados desde el 13 de
  agosto: `modules/contrib/ai_interpolator`, `ai_interpolator_eca` y `ai_interpolator_openai`.
  No se borran a mano porque `composer.json` todavía pide `drupal/ai_interpolator_openai`, y la
  forma correcta de quitarlo es `composer remove`, que en este proyecto no se puede ejecutar sin
  riesgo hasta sanear los conflictos. Va con esa tarea, no antes. No molesta: código
  desinstalado no se ejecuta.
- **Referencias muertas en las carpetas `config/install` de nuestros módulos.** Unos quince
  ficheros dentro de `tec_inventory`, `tec_brands` y `tec_crm` siguen declarando dependencias de
  `ai_interpolator`. Solo se leen al instalar un módulo, así que hoy no hacen nada, pero si
  algún día se reinstalara uno de ellos fallaría. Es limpieza cosmética, sin prisa.
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

### 2026-08-13 — Fase 1: `composer.json` por fin describe la realidad, y aparece por qué falla la página de pedidos

La fase consistía en poner al día el fichero que dice qué módulos tiene este proyecto, sin mover
ni una versión. Estaba muy lejos de la realidad. El inventario cruzado de cuatro fuentes —lo que
pide Composer, lo que tiene apuntado, lo que hay en el disco y lo que Drupal usa de verdad—
salía así: Composer pedía 100 módulos, tenía apuntados 136, en el disco había 160 y Drupal
usaba 148.

Lo que se hizo, todo con `--no-update` para que no se instalara ni se borrara nada:

- **Anclados 12 módulos que entraban por la puerta de atrás.** Estaban en uso pero nadie los
  pedía: llegaban como dependencia de otra cosa. Entre ellos `inline_entity_form` —el del parche
  delicado—, `flag`, `token`, `tamper` y `color`. Si el día de mañana se quita el módulo que los
  arrastraba, desaparecen sin avisar.
- **Quitados 38 que sobraban.** Y aquí una corrección a lo que dijimos el 13 por la mañana: **no
  son paquetes fantasma**. Los 38 están en el disco, con su código y todo; lo que pasa es que
  Drupal no los tiene encendidos. Quitarlos del fichero no borra nada hoy. Es la retirada de
  Commerce entero, la IA, `search_api`, `shield`, `quicklink`, `memcache` y compañía. Antes de
  tocarlos se comprobó que `settings.php` no menciona ninguno.
- **Fuera el `merge-plugin`.** Apuntaba a `modules/contrib/charts/modules/charts_billboard/composer.json`.
  Ni el fichero ni la carpeta `charts` existen, y el módulo no está instalado. El plugin llevaba
  quién sabe cuánto sin hacer nada.
- **Metidos 16 de los 18 huérfanos**, cada uno con la versión exacta que corre, verificada una a
  una contra drupal.org antes de escribirla.
- **Fijados los seis comodines.** Un `*` significa "cualquier versión", que es lo contrario de
  reproducible.

**Los tres módulos con historia.** De los 18 huérfanos, 15 eran versiones publicadas normales.
Los otros tres:

- `pdf_serialization` **ya está resuelto**: es la 2.2.0 más dos parches que estaban tirados
  dentro de su propia carpeta como `4.patch` y `6.patch`. Se comprobó dos veces que aplicados
  sobre la 2.2.0 original reproducen exactamente el código del disco. Ahora viven en `patches/`
  con nombres que se entienden y están declarados en `composer.json`, así que Composer los
  aplicará solo.
- `quicktabs` **no es una versión publicada, es un clon de la rama de desarrollo**. Trae
  `.gitlab-ci.yml` y `phpstan.neon`, que solo aparecen en clones de git, y va por delante de la
  alpha7. Los cambios que lleva de más son de arriba, no manipulaciones de nadie: el JavaScript
  cambia `$.cookie` por `window.Cookies`, que es el arreglo conocido para Drupal 10. Se queda
  fuera de Composer hasta que se decida a qué versión llevarlo.
- `views_entity_form_field` también se queda fuera, por lo mismo que ya estaba anotado: la
  versión del disco es anterior a la 8.x-1.2 y su parche no aplica sobre ella.

**Las siete ramas de desarrollo se quedan como están**, a propósito. Son `eca`, `eck`, `bpmn_io`,
`conditional_fields`, `bootstrap_layout_builder`, `mimemail` y `ultimate_cron`. Fijarlas
significaría moverlas de versión, y esta fase no mueve versiones. El `lock` ya guarda el commit
exacto de cada una, así que una instalación desde cero sí es reproducible; el riesgo es solo si
alguien lanza un `composer update` a pelo.

**El paso que falta y por qué.** Queda sincronizar el `lock`, y no se puede. El ensayo acotado
falla con un mensaje que lo explica todo: **`views_aggregator` 2.1.1 exige núcleo `^10.3 || ^11`
y nosotros corremos 10.2.4**. Es el único módulo del disco en esa situación.

Y esto cierra un círculo. Es exactamente el error 500 de la página de pedidos que diagnosticamos
leyendo el código el 13 de agosto, solo que ahora lo dice Composer por su cuenta y con otras
palabras. No era una casualidad ni un fallo raro del módulo: el sitio lleva tiempo ejecutando un
módulo fuera del rango de versiones que su propio autor declara soportar. **La Fase 1 no se
puede terminar sin subir el núcleo**, y subir el núcleo arregla la página de pedidos de paso.

**El ensayo completo, que es el dato gordo del día.** Un `composer update` de todo, simulado sin
tocar nada, **resuelve sin un solo conflicto**: 29 instalaciones, 107 actualizaciones y 69
retiradas. El núcleo llegaría a 10.6.15 y los avisos de seguridad bajarían **de 82 a uno**.

No se hizo, y no se debe hacer así. Serían las fases 2, 3 y 4 de una sentada, con 107 cambios a
la vez y ninguna forma de saber cuál rompió qué si algo falla. Pero deja probado que el camino
entero está despejado, que era la duda de fondo. Ojo a dos cosas que aparecen en esa lista y hay
que tratar con cuidado cuando toque: `eca_ui` saltaría de la 1.1.5 a la 2.1.22, y `eca` movería
de commit. Eso mueve las transacciones de inventario.

**El informe de `upgrade_status`, que terminó esa misma noche.** 165 proyectos, 84.396 líneas.
23 hallazgos de "arreglar ya" y 18.936 de "revisar a mano". Los 18.936 son ruido de la
herramienta —"llamada a método no definido", "acceso a propiedad no definida"—, los falsos
positivos típicos de analizar código ajeno sin contexto completo. De los 23 importantes,
**ninguno está en nuestro código** y la mayoría están en ficheros de prueba que no se ejecutan
en producción. Los reales son funciones que Drupal 11 retira, en `eck`, `entity_browser`, `flag`
y `flood_control`, y las versiones nuevas de esos módulos ya las tienen arregladas. Ocho
proyectos salen completamente limpios, y uno de ellos es **`TEC Production`**, nuestro módulo
grande.

El inventario quedó guardado como `scripts/inventario-composer.php`. Se vuelve a lanzar con
`drush scr scripts/inventario-composer.php` y no modifica nada, solo cuenta.

### 2026-08-13 — Fase 0 de la actualización: la red de seguridad, y un parche que llevaba meses sin poder aplicarse solo

Antes de tocar una línea del núcleo había que poder volver atrás y poder saber si algo se
rompió. Eso es esta fase. Salieron tres cosas, y la tercera era una bomba de relojería.

**La copia de seguridad, y dos descubrimientos incómodos.** El primero: **todo el trabajo del 12
y el 13 de agosto estaba sin confirmar en Git**: unos 140 ficheros de configuración, la limpieza
de 43 módulos, la retirada de la IA y el backlog entero, todo en el aire.

El segundo: **la carpeta `sites/default/private`, con 1.389 ficheros, no estaba en ninguna copia
y tampoco está en GitHub.** La copia de ficheros del 12 de agosto no la incluía. Llevaba desde
entonces sin respaldo. Ya está copiada.

Se guardó todo lo que Git no protege, en `C:\laragon\backups\pre-actualizacion-*`: la base de
datos (6,6 MB comprimidos, 88 MB de SQL), el árbol de trabajo con `config`, `docs`, `patches`,
`composer.json`, `composer.lock` y `modules/custom` (2.648 ficheros), `core` con `vendor` (35.941
ficheros, 173 MB) y `private` (1.491 entradas, 171 MB). Los ficheros de usuario siguen cubiertos
por la copia del 12, porque lo único que ha cambiado desde entonces son ficheros de caché
regenerados.

**Y se probó la restauración, que no es lo mismo que probar que el fichero se lee.** El volcado
se cargó entero en una base de datos aparte y se compararon los recuentos: **449 tablas en ambas,
y cero diferencias** en escandallos, líneas, pedidos, materiales, usuarios y procesos de ECA. Las
únicas tres entradas de configuración que faltaban eran las que crea `upgrade_status`, instalado
después del volcado. La base de datos de prueba se borró al terminar.

De paso se aprendió que **empaquetar el proyecto entero no funciona en esta máquina**: el
antivirus inspecciona cada fichero y `tar` se quedó dieciséis minutos sin escribir un solo byte.
Hay que copiar por partes. Y un detalle que hace perder tiempo: si se añade Git al PATH, `tar`
deja de ser el de Windows y pasa a ser el de Unix, que interpreta `c:/...` como un servidor
remoto y falla. Con Git en el PATH hay que llamar a `C:\Windows\System32\tar.exe` por su ruta.

**La lista de comprobación ya es un programa.** Estaba en la cabeza y en el chat; ahora está en
`scripts/comprobacion.php` y se ejecuta con `drush scr scripts/comprobacion.php`. Hace **35
comprobaciones**: cuenta todo el contenido contra las cifras de referencia, verifica los 146
módulos y los 36 procesos de ECA, crea entidades de verdad para ver si los seis automatismos
reaccionan, y revisa que no queden banderas de bloqueo colgadas ni errores nuevos en el
registro. Lo que crea lo borra y lo que modifica lo restaura. Se pasó tres veces esta noche,
siempre 35 de 35. **Es la orden que hay que ejecutar después de cada paso de la actualización.**

**El parche de `inline_entity_form` llevaba sin poder aplicarse solo desde siempre, y nadie
sabía por qué.** El arreglo estaba en el fichero, pero puesto a mano, con un comentario que
decía literalmente *"composer patch could not auto-apply on this machine"*.

La causa, encontrada esta noche, tiene tres capas. La superficial: **el fichero del parche tenía
finales de línea de Windows y el módulo los tiene de Unix**, y Composer aplica los parches
primero con `git apply`, que es estricto y rechaza esa mezcla de plano, y luego con el programa
`patch`, que sí la tolera pero **no estaba en el PATH**. Sin las dos vías, el parche fallaba
siempre.

La segunda: **Composer no estaba configurado para detenerse ante un parche fallido**, así que
seguía adelante diciendo que todo había ido bien.

Y la de abajo del todo, que es la que explica por qué el problema era eterno: **Git en esta
máquina tiene `core.autocrlf = true`, y el `.gitattributes` que trae Drupal fija finales Unix
para `.php`, `.module` y `.yml`, pero no dice nada de `.patch`.** O sea que Git sacaba el parche
convertido a finales de Windows **en cada checkout**. Cualquiera que lo hubiera arreglado a mano
lo habría visto romperse otra vez a la siguiente descarga del repositorio, sin motivo aparente.

Se arregló en cuatro pasos: una línea `*.patch text eol=lf` en `.gitattributes`, que es el
arreglo de verdad y el único permanente; el parche convertido a finales Unix; el fichero del
módulo dejado exactamente como lo produce el parche, porque antes había dos versiones distintas
del mismo arreglo; y `"composer-exit-on-patch-failure": true` en `composer.json`.

Se comprobó del modo que importa: se borró el parche, se dejó que Git lo sacara de nuevo, y sale
con finales Unix y aplicando limpio. Ahora, además, `git apply --check --reverse` sirve para
saber si el parche está puesto, cosa que antes era imposible.

**Y se comprobó solo, por accidente.** Al instalar `upgrade_status`, Composer decidió borrar y
reinstalar `inline_entity_form` para volver a parchearlo — cosa que hace sin avisar y por su
cuenta. Esta vez el parche se aplicó bien. Sin el arreglo de esta noche, ese `composer require`
inocente y sin relación ninguna se habría llevado el arreglo por delante en silencio. **El
programa `patch` está en `C:\laragon\bin\git\usr\bin`**, y hay que añadir esa carpeta al PATH
antes de lanzar cualquier orden de Composer.

**El informe de `upgrade_status` confirma lo que decía la auditoría del código propio.** Se
instaló la versión 4.3.10 (cinco paquetes nuevos, cero cambios en lo ya instalado) y se analizó
el código nuestro: **ni una sola llamada a funciones que Drupal 11 elimine**. `tec_production`,
el módulo grande, sale limpio del todo. Los otros cinco solo necesitan cambiar la línea
`core_version_requirement`. Dos de ellos, `tec_brands` y `tec_crm`, ni siquiera están instalados.

### 2026-08-13 — Diagnóstico de Composer: el bloqueo era menor de lo que creíamos, y el descuadre mayor

Cuatro comandos de solo lectura, ninguno modificó un fichero (comprobado por huella digital
antes y después). Tres conclusiones que cambian el plan:

**La subida del núcleo no está bloqueada.** Simulada con `composer update drupal/core-recommended
--with-all-dependencies --dry-run`, resuelve sin un solo conflicto y llega hasta la **10.6.15**,
no solo la 10.3. Son 22 actualizaciones y 3 retiradas, y no toca ningún módulo contribuido.

La idea de que "en este proyecto no se puede actualizar" no venía de meses de intentos: nació el
9 de agosto, cuando un `composer update` a pelo rompió `inline_entity_form`, y se dio por buena
sin volver a comprobarla. Cuatro días de suposición, no meses. Lo que está atascado es el
`update` general; una actualización dirigida no lo está.

**El descuadre de versiones es de 24 módulos, no de 5.** Y lo importante es *por qué* nadie lo
vio: Composer no lee los ficheros de los módulos, se fía de su contabilidad interna en
`vendor/composer/installed.json`, que sigue diciendo `ds` 3.19.0 cuando en disco hay un 3.22.
Por eso `composer install --dry-run` responde "nada que hacer" con total tranquilidad. El
detalle está en el apartado 8.

**Hay 82 avisos de seguridad conocidos**, 42 de ellos del núcleo y varios críticos, más Twig
(16), Guzzle (9 más 4 de `psr7`), y uno suelto en `eca`, `entity_browser` y `mail_login`. La
subida del núcleo se lleva por delante casi todos.

De paso, `composer validate` confirmó que **el `lock` no está sincronizado con `composer.json`**,
y catalogó los 15 comodines `*` y las 11 ramas de desarrollo.

### 2026-08-13 — Prueba funcional tras la limpieza: el ERP está intacto, y un problema viejo que salió a la luz

Después de quitar 43 módulos y tocar los procesos de ECA, la pregunta era simple: ¿el ERP hace
lo mismo que antes? **Sí.** Se probaron seis automatismos creando entidades de verdad y
comprobando si reaccionaban. Los seis funcionan:

- Un artículo de escandallo nuevo **se pone el título solo** a partir del material.
- Al modificarlo, **copia la cantidad** al campo de entrada.
- Si lleva talla, **se engancha solo a la variación de talla** (la talla pasó de 24 a 25).
- Un movimiento de inventario **se engancha solo al material** (pasó de 2 a 3 movimientos).
- Una variación de color **se pone el título sola** a partir del color.
- Una línea de pedido **recalcula su total**: 1.200 × 2 dio 2.400.

Todo lo creado durante la prueba se borró después, y la comprobación final lo confirma.

**La prueba de que no se ha perdido nada.** Se cargó la copia de las 02:58 —anterior a que se
tocara el primer módulo— en una base de datos aparte y se compararon los recuentos de todas las
tablas de contenido contra las de ahora: **cero diferencias**. Los 4.773 artículos de
escandallo, los 178 elementos de escandallo de línea, las 85 líneas de venta, los 869
materiales, los 315 ficheros, los 7 usuarios y hasta las banderas, todo igual. La base de datos
de pruebas se eliminó al terminar.

**Lo que salió por el camino, y que es anterior a nosotros.** Una de las siete comprobaciones
falló al principio, y perseguirla destapó un problema viejo que conviene conocer:

De 85 líneas de venta, elegí la 1567 al azar y resultó ser una de las cinco averiadas. Al
cambiarle la cantidad, el proceso arranca, escribe *"Running"* en el registro y **se para en
seco**: sin error, sin excepción, sin aviso. El total no se recalcula y, peor, **la bandera de
bloqueo se queda puesta**. Esa bandera existe para que el proceso no entre en bucle al
guardarse a sí mismo, y mientras esté puesta ese proceso no volverá a ejecutarse nunca para esa
línea. Falla en silencio y se queda averiado para siempre.

La causa: la línea 1567 tiene 24 elementos de escandallo, y **cuatro de ellos apuntan a
materiales que ya no existen** (los términos 2321, 2456 y 2524). El proceso recorre el
escandallo, intenta cargar el material fantasma, no lo encuentra y muere ahí.

Y el alcance es mayor de lo que parecía: **2.439 de los 4.773 artículos de escandallo apuntan a
materiales borrados**, 88 materiales fantasma en total, más 12 movimientos de inventario y 8
elementos de escandallo de línea. Alguien borró esos 88 materiales en su día sin limpiar las
2.459 referencias que apuntaban a ellos.

**No es cosa nuestra, y está demostrado.** En la copia de las 02:58, anterior a toda la
limpieza, esos tres términos ya no existían, los mismos cuatro elementos ya apuntaban a ellos,
la línea 1567 ya tenía cantidad 10 y total 9.500, y ya había exactamente 44 líneas con total.
Nunca hemos borrado términos de taxonomía: desinstalar un módulo se lleva campos y
configuración, no contenido.

Dos cosas que quedan apuntadas de aquí: el borrado de datos de prueba se lleva por delante casi
todo esto, pero **el fallo silencioso no**, y volverá a morder con datos reales el día que
alguien borre un material que esté en uso. Está en deuda técnica.

Una nota para el futuro: los mensajes que ECA escribe en el registro **no son errores** aunque
algunos aparezcan como tales. Son mensajes de traza con los que los procesos van contando por
dónde van, y casi todos están puestos en nivel *depuración*. Ver "Running: ..." en el registro
es normal. La excepción, que es una errata, está apuntada en deuda técnica.

### 2026-08-13 — Fuera la IA: de 148 a 146, y una cascada que casi se lleva nueve automatismos

Quedaban dos módulos de la aventura de la inteligencia artificial del programador anterior,
`ai_interpolator` y `ai_interpolator_eca`. Ya no están. El ERP se queda en **146 módulos**.

Lo que bloqueaba quitarlos eran cuatro procesos de ECA que, sobre el papel, llamaban a la IA.
Al leerlos apareció lo que de verdad pasaba: **la IA ya estaba muerta desde hacía tiempo, solo
que nadie lo había rematado.**

- En los dos procesos que estaban encendidos, `process_p2q045n` (poner los datos de un artículo
  de escandallo) y `process_uhiwdqa` (importar artículos de escandallo), la acción de IA seguía
  en el fichero pero **no la apuntaba nadie**: le habían cortado todas las conexiones de
  entrada. Alguien incluso las había renombrado a mano a "(disabled)" y "(disabled — local
  parse)". Ese "local parse" sugiere que el cálculo se sustituyó por uno hecho en el propio
  servidor, sin IA.
- Los otros dos, `process_utltgmh` y `process_lygeesg`, eran **clones apagados**, las versiones
  1.3 y 1.4 de un proceso cuya versión viva es la 0.1. Nunca se activaron. Se han borrado
  enteros por decisión del dueño; quedan en el historial de Git y en las copias.

El campo propio de la IA, `ai_interpolator_status`, tenía 4.651 filas y ni un dato de negocio:
4.579 decían `pending` y 72 `finished`. Es decir, se lanzó sobre 4.651 artículos y se abandonó
habiendo terminado 72. Desapareció solo al desinstalar.

**Una trampa que había que desactivar antes.** En ECA cada proceso vive por duplicado: el
diagrama que se dibuja en el editor visual y la configuración compilada que es la que de verdad
se ejecuta. Aquí **no coincidían**: en la compilada la IA estaba desconectada, pero en el
diagrama seguía enchufada, porque quien la desactivó editó el YAML a mano y no tocó el dibujo.
Con el editor visual instalado, como lo está, habría bastado que alguien abriera ese proceso y
le diera a guardar para que la IA volviera. Se ha limpiado también el BPMN de los dos diagramas
y ahora dibujo y ejecución dicen lo mismo.

**El susto de la noche.** Al desinstalar, Drupal borró **nueve procesos de ECA** que no tenían
nada que ver con la IA: duplicar producto, duplicar color, duplicar talla, los datos de línea de
pedido, el gestor de mutaciones de stock y tres más. El mecanismo es el que hay que recordar:
esos procesos dependen de campos como `field_tec_inventory` o `field_tec_colors`, y esos campos
llevaban ajustes del módulo de IA colgando. Drupal arregló los campos, pero **arrastró en
cascada y borró entero todo lo que dependía de ellos**. Se recuperaron los nueve importando solo
esos ficheros desde `config/sync` con `--partial`, que no borra nada de lo demás. Verificado
después: 36 procesos en base de datos y 36 en disco, los mismos que había menos los dos clones.

**La lección, para la próxima vez que se desinstale un módulo con muchos tentáculos:** mirar
antes qué configuración depende de la que va a cambiar, y contar los procesos de ECA antes y
después. La cuenta de módulos sale bien aunque por debajo se hayan perdido automatismos.

Comprobado al terminar: cero rastro de `ai_interpolator` en la configuración y en la base de
datos, configuración y disco sincronizados, ningún error nuevo en el registro, y las páginas
`/`, `/o/queue`, `/stock`, `/production/log` y el listado y el editor de ECA cargando bien. Hizo
falta, otra vez, vaciar las tablas de caché a mano: el editor visual siguió ofreciendo la acción
de IA en su paleta hasta que se vaciaron.

Copia previa en `c:\laragon\backups\pre-quitar-ia-20260813.sql.gz`, y los nueve procesos
rescatados en `c:\laragon\backups\eca-restaurar-20260813`.

### 2026-08-13 — Cuarenta y un módulos fuera: de 189 a 148

El ERP tenía 189 módulos activos. Ahora tiene 148. Ninguna pantalla se ha resentido.

La pregunta de partida era cuánto de lo que dejó instalado el programador anterior no servía
para nada. Para responderla se lanzaron cuatro revisiones en paralelo: una construyó el árbol
de qué módulo depende de cuál, otra buscó pruebas de uso real de cada uno de los 189, otra
comprobó en drupal.org la compatibilidad de cada uno con Drupal 11, y la cuarta revisó nuestro
propio código.

**Cruzar las cuatro fue lo que evitó tres estropicios.** Por separado, la revisión de uso daba
por muertos a `csv_serialization` y a `media_library_form_element`, y el árbol de dependencias
demostró que el primero lo necesita `views_data_export` —que genera los PDF y los Excel— y el
segundo lo necesita `bootstrap_styles`. Y daba por muerto a `smtp` porque está apagado; lo
está, pero porque **todavía no lo hemos configurado**, y es justo lo que hace falta para que
funcionen las recuperaciones de contraseña. Los tres se quedaron.

Lo que se fue, en cuatro tandas y de uno en uno:

- **Veintiuno muertos del todo**, sin nadie que dependiera de ellos ni configuración que los
  citara: `autocomplete_deluxe`, `state_machine`, `physical`, `workflows`, `profile`,
  `batch_jobs`, `queue_ui`, `entity_reference_modal`, `entity_reference_revisions`,
  `field_tools`, `base_field_override_ui`, `layout_builder_tabs`, `layout_builder_blocks`,
  `prepopulate`, `comment`, `history`, `selective_better_exposed_filters` y los cuatro de
  `jquery_ui` que no usaba nadie.
- **Nueve submódulos de ECA** que no aportaban ni un plugin a los 38 procesos montados:
  `eca_access`, `eca_cache`, `eca_config`, `eca_form`, `eca_misc`, `eca_queue`, `eca_render`,
  `eca_user` y `eca_vbo`.
- **Siete instalados y apagados a mano**: `shield`, `automated_cron`, `pwa_extras`, `quicklink`,
  `entity_browser_enhanced`, `ds_extras` y `file_resup_media_library`. El caso de `quicklink`
  resume bien el conjunto: estaba configurado para no ejecutarse ni con el usuario identificado
  ni en rutas de administración, o sea, nunca, porque en un ERP eso es el cien por cien del
  tiempo.
- **Cuatro que necesitaban un paso previo**: `features` antes que `config_update`, `pace`, y
  `file_resup`, que hubo que liberar antes borrando su línea en `tec_inventory.info.yml`.

**Ese último detalle merece quedar escrito.** Nuestro propio módulo de inventario declaraba
como dependencias obligatorias tres experimentos del programador anterior: `ai_interpolator`,
`ai_interpolator_eca` y `file_resup`. Por eso Drupal se negaba a desinstalarlos. No es que el
inventario los usara; es que alguien escribió que los necesitaba. La línea de `file_resup` se
quitó ese día y las dos de la IA esa misma noche, al desinstalar los dos módulos (ver la entrada
anterior). También las declaraban `tec_brands` y `tec_crm`, pero esos dos módulos no están
instalados, así que no estorbaban.

**Dos sustos, los dos resueltos.** El primero: nada más terminar, el sitio devolvía error 500
al entrar. La causa era la caché rancia del servidor web, que seguía llamando al módulo de
comentarios recién desinstalado; se arregló vaciando las dieciocho tablas de caché
directamente en la base de datos, que es el mismo remedio que hizo falta la vez anterior que se
tocaron módulos.

El segundo fue más instructivo. Un pedido de venta, el 348, seguía dando error 500 después de
la limpieza. En lugar de suponer, se restauró la copia de seguridad **anterior** a la limpieza
y se cargó ese mismo pedido: **también fallaba antes**. O sea que el error ya estaba ahí y no lo
había provocado la limpieza. Es el fallo de `views_aggregator` descrito en la sección 9. Los
otros ocho pedidos de venta que se probaron cargan bien.

Comprobado al terminar: los 38 procesos de ECA intactos, la configuración exportada sin
diferencias con la base de datos, y nueve pantallas más seis fichas de datos cargando sin un
solo error, entre ellas la cola de producción, el tablero de stock, el registro de producción,
un producto, un pedido, una orden de compra, un material y las fichas de un cliente y un
proveedor.

Las copias de seguridad de antes y después están en `c:\laragon\backups`, como
`pre-limpieza-modulos-20260813.sql` y `post-limpieza-modulos-20260813.sql`.

**Falta llevarlo al servidor**, y hay un orden obligado: primero sube solo la configuración, con
el código de los módulos todavía presente, para que Drupal pueda ejecutar sus rutinas de
desinstalación; y solo después, en un segundo viaje, se borra el código. Al revés dejaría restos
en la base de datos.

### 2026-08-12 — El ERP, publicado en internet

Está en **`https://erp.anvfightgear.com`**, en el servidor de Singapur. Desde el primer envío
de archivos hasta tener la página de acceso funcionando con certificado pasaron unos cuarenta
minutos.

Lo que se montó: el código traído desde GitHub con una llave de despliegue de solo lectura
—el servidor puede descargar el repositorio pero nunca escribir en él, así que aunque alguien
entrara en la máquina no podría tocar el código—, los 254 paquetes de Composer, la base de
datos con sus 460 tablas, los 3.950 archivos de imágenes y documentos, Apache, y el
certificado de Let's Encrypt con renovación automática ya probada en simulacro.

Los tres ajustes de `settings.php` quedaron corregidos, y de paso dos más que no estaban en la
lista: los errores ya no se enseñan en pantalla sino que van al registro, y el ERP se conecta a
la base de datos con un usuario propio en vez de con `root`. El propio `settings.php` está
puesto de forma que solo lo pueden leer el sistema y el servidor web, y comprobado desde fuera
que devuelve un 403.

**Dos sustos que conviene recordar.** El primero: el comprimido hecho con Windows solo
descomprimía 659 de los 3.950 archivos y se paraba en silencio, sin dar error, porque Windows
no guarda los permisos que Linux necesita. Y engañaba dos veces, porque la prueba de
integridad del comprimido decía que estaba perfecto y el tamaño en disco coincidía byte a
byte. Solo se detecta contando los archivos ya descomprimidos. De haber pasado por alto,
el ERP habría abierto con casi todas las imágenes rotas. Se rehizo con `tar` y entraron todos.

El segundo: **`docs/backlog.md`, este mismo archivo, se podía descargar desde internet sin
contraseña**, con los planes del servidor, el historial del incidente de la clave y las notas
internas dentro. Drupal bloquea por su cuenta los `.yml` y los `.php`, pero los `.md` no los
conoce porque no existían cuando se escribieron esas reglas. Ya está bloqueado, junto con el
README y los `.txt` de instalación, en los dos ficheros de configuración de Apache — importa
que sean los dos, porque Certbot hace una copia para la versión segura del sitio y desde ese
momento las dos van por su cuenta.

Ambos están documentados como trampas 8 y 9 en el procedimiento de despliegue del README de
`tec_production`, junto con el resto de correcciones que el ensayo en local no pudo anticipar.

Veinte minutos después de publicarlo, un rastreador automático de internet (LeakIX) ya había
encontrado el formulario de acceso e intentado entrar. Es ruido de fondo, le pasa a cualquier
cosa que se publique, y el sitio lo rechazó — pero es el motivo por el que ninguna cuenta
puede quedarse con una contraseña floja.

### 2026-08-12 — El servidor de Singapur, creado

Existe y responde. Se llama `erp-anv-sgp1`, su dirección pública es **188.166.255.1**, corre
Ubuntu 24.04.4 LTS con 2 GB de memoria, 1 CPU y 50 GB de disco, y cuesta 15,60 al mes con las
copias diarias incluidas, que se hacen de 3 a 7 de la madrugada hora de Tailandia.

Se entra con llave SSH, sin contraseñas. La llave se creó ese mismo día en el PC del dueño
(`C:\Users\Acer\.ssh\id_ed25519`, tipo ed25519, sin frase de paso para que los despliegues no
pidan teclear nada). Hay copia de la mitad privada en LastPass, en una nota segura llamada
"Llave SSH - servidor ERP (erp-anv-sgp1)", así que perder el ordenador ya no significa perder
el acceso al servidor.

Conviene saber además que perder la llave nunca sería fatal: como la cuenta de DigitalOcean es
del dueño, siempre se puede entrar por la consola web del panel, restablecer el acceso y
registrar una llave nueva. Sería un rato de incordio, no un desastre.

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
