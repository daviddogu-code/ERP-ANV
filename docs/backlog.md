# Backlog — ERP ANV

> Registro vivo de tareas pendientes y de decisiones ya tomadas.
> Escrito en español porque el lector principal es el dueño del proyecto.
> Las otras notas de `docs/` están en inglés y son documentación técnica; esta no.
>
> Última actualización: 2026-08-19.

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

- **Llevar al servidor el salto a Drupal 11.4.5.** Hecho en local el 13 de agosto; el servidor
  sigue en **10.2.4 con los 82 avisos de seguridad**. Es lo más urgente de la lista.

  **La máquina ya sirve, no hay que tocarla.** Se montó a propósito con PHP 8.3 y MySQL 8.4 sobre
  Ubuntu 24.04 para que coincidiera con Laragon, y resulta que eso es exactamente lo que pide
  Drupal 11. La decisión de "aburrirse a propósito" se acaba de pagar sola.

  **Hay que tener a mano la contraseña de `sudo` de `david`.** Se descubrió el 13 de agosto: por
  SSH se entra sin problema con la llave, pero todo lo que necesita el despliegue —tocar
  `settings.php`, importar la base de datos, cambiar dueños de carpetas, reiniciar Apache— pide
  `root`, y `root` no admite entrar directamente. Sin esa contraseña el despliegue se para en el
  primer paso. Está en LastPass o en la cabeza del dueño; conviene sacarla antes de empezar y no
  a mitad de faena.

  **Propuesta: reemplazar la base de datos entera con la de local**, en vez de actualizar la del
  servidor paso a paso. Local es la copia maestra, allí no hay datos reales, y así el servidor
  queda idéntico a lo que se probó. **Y desde el 15 de agosto ya no se pierde nada al hacerlo**:
  aquí se decía que el precio era la cuenta de Lukpla, que existía en el servidor y no en local, y
  ese precio ha desaparecido porque ese día se crearon aquí las tres cuentas de los empleados
  —`lukpla`, `coo` y `manager`—, así que viajan con la base. Lo único que hay que hacer al llegar es
  ponerles la contraseña. Aun así, antes hay que pasar `drush config:status` **en el servidor** y
  contar los procesos de ECA, que allí deben ser **36** antes de desplegar y **30** después. La
  explicación de por qué bajan cuatro está unas líneas más abajo, en la comprobación de los
  procesos.

  De los tres tropiezos que aquí se anotaron como "volverán a salir allí", **dos eran de
  Windows** y no se repetirán en Linux: que `drush updatedb` no se reconociera y que las
  versiones de esquema no se guardaran. El tercero sí volverá, porque el servidor tiene su propio
  `vendor` construido desde git y `core-vendor-hardening` lo ensucia: es el *"Source directory
  has uncommitted changes"*. El arreglo ya está probado en local, borrar `vendor` entero y
  `composer install`, que además lo reinstala todo desde archivo y cierra esa clase de problema
  para siempre.

  **Y una que no viaja por git, ya comprobada.** `settings.php` está en `.gitignore`, y con
  razón, así que el apagado de `rebuild_access` del 13 de agosto no llega al servidor con el
  `git pull`. Se temía que allí siguiera en `TRUE`, porque ese fichero se escribió a mano y
  parecía probable que se hubiera copiado del de local. **No es el caso: está en `FALSE`**,
  comprobado el 13 de agosto. No hay ningún agujero abierto por ahí. El fichero del servidor no
  es una copia del de local sino uno nuevo escrito en el propio despliegue, de 1.569 bytes
  frente a los 37.314 de aquí, y de paso quedaron verificados los otros cuatro ajustes:
  `update_free_access` en `FALSE`, el dominio de confianza solo `erp.anvfightgear.com`, los
  archivos privados en `/var/www/erp-private` (fuera de la carpeta que sirve Apache) y los
  errores en el registro y no en pantalla.

  Aun así la lección de fondo sigue en pie: **`settings.php` se mantiene a mano en cada sitio**,
  así que conviene revisarlo entero al desplegar, no solo esta línea.

  Y dos comprobaciones nuevas, ahora que el `lock` manda de verdad. **Pasar
  `scripts/comparar-con-original.php` en el servidor antes de desplegar**: si allí hay algún
  módulo retocado a mano que aquí no tenemos localizado, el `composer install` del despliegue se
  lo lleva por delante sin decir nada. En local había tres. Y **pasar `scripts/ramas-dev.php`**,
  que descubre lo mismo pero en los módulos anclados a un commit, que es donde se nos escondió
  el fallo crítico de ECA.

  Sobre ese fallo: el servidor sigue con **ECA 1.1.7**, pero **el agujero ya está tapado** desde el
  14 de agosto por la tarde, sin desplegar nada: se desinstaló `eca_ui` allí y con ella se fueron las
  rutas que el CSRF de `SA-CONTRIB-2025-031` necesitaba. Detalle abajo, en Hecho. Sigue siendo el
  motivo más fuerte para no dejar el despliegue para nunca —la versión vulnerable continúa instalada,
  solo que sin puerta— pero ya no es una cuenta atrás.

  **Y ojo con una divergencia a propósito**: allí `eca_ui` está desinstalada y aquí encendida. No hay
  que hacer nada, se resuelve sola en el despliegue, cuando la base de datos de local llegue con
  `eca_ui` ya sobre ECA 2.1.22, que es la versión parcheada. Está escrito para que dentro de tres
  semanas no parezca un descuadre.
- ~~**Un cabo suelto de dos minutos, mejor antes de desplegar: desinstalar `upgrade_status`.**~~
  Hecho el 14 de agosto de 2026. De paso hubo que decidir qué pasaba con `update`, que estaba
  encendido solo porque `upgrade_status` lo arrastra: **se queda, y ahora a propósito**. Es el
  módulo del núcleo que avisa de las alertas de seguridad, o sea exactamente lo que le faltaba a
  este sitio cuando acumuló ochenta y dos sin que nadie se enterara. Sus ajustes ya están
  exportados, así que `config:status` dice por fin **"no differences"**. Si se decide apagarlo, es
  un `drush pm:uninstall update` y bajar la cifra de módulos de la comprobación de 146 a 145.
- ~~**Decidir hasta dónde se sigue con las fases 3 y 4.**~~ Decidido y hecho el 13 de agosto: se
  fue hasta el final. ECA a la 2.1.22, los temas al día y el núcleo en Drupal 11.4.5.

~~**Confirmar en Git el trabajo del 12 y el 13 de agosto.**~~ Hecho el 13 de agosto en cinco
commits, **subidos a GitHub esa misma noche**: la limpieza de módulos, la red de seguridad de la
Fase 0, el saneado de Composer, la preparación del último tramo y el salto a Drupal 11.

El ERP ya está publicado en `https://erp.anvfightgear.com` desde el 12 de agosto.

**La revisión de Lukpla queda aparcada** por decisión del dueño el 13 de agosto: se le dirá que
de momento no entre, para poder trabajar sin la restricción de no romperle nada. Lo que hay
preparado para cuando se retome:

- ~~**Crear la cuenta de Lukpla.**~~ Hecha, con rol *supervisor*, directamente en el servidor. Y
  **desde el 15 de agosto existe también en local**, con las de `coo` y `manager`, así que deja de
  ser una cuenta que solo vive en un lado.
- **Llevar al servidor la limpieza de módulos del 13 de agosto**, con un cuidado que antes no
  hacía falta. **Local y servidor ya no son la misma cosa**, y lo que importa de esa diferencia no
  son los usuarios sino lo que ella haya podido tocar: si ha empezado a revisar puede haber cambiado
  vistas o pantallas. Los usuarios son contenido y no corren peligro, pero **la configuración sí**:
  un `drush cim` desde local machacaría cualquier cambio que ella haya hecho. Antes de desplegar hay
  que ejecutar `drush config:status` **en el servidor** y mirar si ha divergido. Si ha divergido,
  primero se traen sus cambios, y luego se sube la limpieza.

  Y una comprobación más, por lo que pasó al quitar los módulos de IA: **contar los procesos de
  ECA en el servidor antes y después** de desplegar. Al desinstalar en local, Drupal borró nueve
  procesos en cascada sin avisar, y la cuenta de módulos salía bien igual.
  `drush sql:query "SELECT COUNT(*) FROM config WHERE name LIKE 'eca.eca.%'"`.

  **La cifra que hay que esperar cambió, y conviene tenerlo claro antes de contar, porque si no
  este control se lee al revés.** El servidor sigue en **36**, porque allí no se ha desplegado nada.
  Local está en **30**. El 14 de agosto se borraron cuatro procesos que hacían el trabajo del
  sistema de unidades opcionales de Óscar y que sobraban al erradicarlo —`rxuimsq`, `uix9n5i`,
  `pehrnr8` e `idtd6ah`—, y el 18 de agosto los dos últimos clones apagados, `darq39m` e `icpsbgv`.
  O sea que al desplegar la cuenta **baja a propósito de 36 a 30**, y eso es la señal de que ha ido
  bien, no de que se haya perdido nada. Lo que este control busca es lo otro: una bajada que nadie ha
  pedido. La comprobación del ERP ya exige 30, así que si algún día se pierde un proceso por el
  camino, salta ahí.
- **Preparar dónde apunta lo que vea**, aunque sea una hoja de cálculo compartida. Por chat
  se evapora en tres días.
- **Darle un encargo concreto**, empezando por compras, en vez de un "míralo a ver qué te
  parece".
- **Confirmar si se maneja en inglés**, que es el idioma en el que está el ERP entero. Y no es una
  pregunta de cortesía: el tailandés que hay configurado **no traduce nada del ERP**, solo cuatro
  palabras del menú de administración de Drupal. Está explicado en el apartado de idiomas de la
  sección 3. Si ella no se maneja en inglés, es ella misma quien tendría que escribir las
  traducciones.

## 2. Esta semana

- **Guardar los diez códigos de recuperación de Namecheap** fuera del móvil, en Drive o
  impresos. Namecheap no permite dos métodos de 2FA a la vez, así que si se pierde el
  teléfono esos códigos son la única forma de entrar en el dominio.
- **Esconder las líneas sin cantidad en el formulario de recibir.** Lo pidió el dueño el 17 de agosto
  con un caso concreto: en `/tec_order/866` la línea de PA-55 no sale porque su cantidad es 0, y eso
  está bien, pero en `/tec_order/866/receive` sí sale. Apartado a propósito desde entonces, y de paso
  quedó medido: **la línea de código son cinco minutos y el resto no.**

  **Qué regla se usa, ya decidido.** La ficha tiene esa regla escrita como filtro de Views,
  `field_tec_quantity_value > 0` en el bloque *Purchase order block*
  (`views.view.tec_order_sales_order_line_items.yml:4826`), y es por eso que la línea no salía allí.
  Recibir debe usar **ese mismo criterio y no otro**. Si usara «no queda nada por llegar» esconderá
  también las líneas ya servidas del todo, y entonces el albarán de papel tendría cinco líneas y la
  pantalla dos, justo cuando lo que se está haciendo es cotejar uno con otra.

  **Lo que de verdad cuesta es el pedido cuyas líneas son todas cero.** Al esconderlas la tabla se
  queda vacía, y entonces el mensaje de tabla vacía dice *"This purchase order has no lines"*
  (`ReceiveForm.php:143`), que es mentira —líneas tiene, cantidad no—, y `validateForm()`
  (`ReceiveForm.php:306`) exige una cantidad o una casilla marcada, así que ese formulario **solo
  puede acabar en un error**. Hay que reescribir el mensaje y decidir si el botón Receive debe
  aparecer siquiera en un pedido así. La cola ya lo resuelve por su lado, porque un pedido sin nada
  que perseguir no sale en pantalla; la ficha sí lleva ahí.

  **Y por qué se pudo aparcar sin riesgo.** Esconder filas no puede estropear una recepción:
  `submitForm()` recorre lo que llega del formulario (`ReceiveForm.php:332`), así que una línea que no
  está simplemente no existe para el guardado, y `report()` vuelve a leer el pedido de la base de
  datos para decidir si queda algo por llegar. El riesgo es cosmético.

  **Hacerlo junto al detalle feo de la sección 6**: la misma regla está escrita dos veces en Views con
  dos redacciones que además no son equivalentes, porque una cantidad negativa pasa el `!= 0` y no
  pasa el `> 0`. Si el formulario añade una tercera en PHP van tres. Lo ordenado es que la pregunta
  «¿esta línea cuenta?» viva junto a `Purchasing::outstanding()` y que las tres pantallas la
  consulten.
- **Limpiar los mensajes de registro de los procesos de duplicar.** Un solo clic de duplicar un color
  escribe veintiséis líneas en el registro, y la mitad salen con el hueco sin rellenar:
  `%token__entity`, `List: %token__bomOriginal`, `The size name is: [size:name]`. Están escritos con
  una sintaxis de plantilla que ese sitio no entiende, así que imprimen el nombre del hueco en lugar
  de su contenido. Hay incluso uno que dice «Finished running: TEC Color variation: Set data values
  on Insert» mientras informa del nombre de una talla, que es un copiar y pegar de otro proceso. No
  rompe nada y por eso lleva ahí desde siempre; el problema es el día que haya que buscar un fallo de
  verdad ahí dentro. Descubierto el 18 de agosto al mirar el registro de un duplicado que sí
  funcionó.

- **Limpiar los setenta ficheros que git da por modificados sin estarlo.** Descubierto el 18 de agosto
  al ir a confirmar el trabajo de la noche: `git status` lista unos setenta ficheros como cambiados
  —`.gitignore`, los cuatro parches, dos guiones `.ps1` y sesenta y tantos `.php`— pero `git diff`
  de cualquiera de ellos sale **vacío**. No hay ningún cambio real dentro, así que ninguno se metió en
  los commits de esa noche; el problema es que ensucian la lista y hay que separar el ruido a mano
  cada vez que se mira qué queda por confirmar.

  Es cosa de finales de línea, pero **no está diagnosticado del todo y conviene no darlo por sabido**.
  Git avisa de que *"in the working copy of '.gitignore', LF will be replaced by CRLF the next time
  Git touches it"*, o sea que `core.autocrlf` está en `true` en esta máquina. Eso explica los ficheros
  sin extensión reconocida, como `.gitignore` y los `.ps1`, que no tienen regla en `.gitattributes`.
  Lo que no explica es por qué salen también los `.php` y los `.yml`, que ahí sí están declarados
  `text eol=lf`.

  La vía es `git add --renormalize .` y mirar qué queda preparado: si no aparece nada, era solo
  índice y basta con confirmarlo; si aparece contenido de verdad, entonces hay algo que revisar antes
  de guardar nada. Media hora contando la lectura, y mejor hacerlo cuando no haya trabajo a medias
  encima, porque toca setenta ficheros de golpe y se come cualquier diff que haya pendiente.

- **Terminar el modelo de marcas y clientes. Está empezado: el primer paso se hizo el 19 de agosto**
  y está contado abajo, en Hecho. Lo que queda es el cambio de fondo, decidido ese mismo día a raíz de
  una observación del dueño: *una empresa puede comprar varias marcas, y la misma marca la pueden
  comprar dos clientes distintos.* Hoy el ERP dice lo contrario en dos sitios a la vez, y ninguno de
  los dos aguanta ese caso.

  **El producto apunta a un cliente** (`field_tec_customer` en `tec_product`, obligatorio) y **la marca
  también** (`field_tec_customer` en el vocabulario, de un solo valor). Dos caminos para la misma
  pregunta, que pueden contradecirse sin que nadie avise, y el de la marca además no se usa para nada:
  es decorativo y engaña.

  El modelo acordado es: **los productos van dentro de la marca, y el cliente elige qué marcas le
  hacemos**, en su ficha y en el orden que decida el dueño. Los pasos:

  1. Un campo nuevo en las fichas de contacto —`tec_contact_organization` y `tec_contact_person`—, de
     varios valores y arrastrable, apuntando a marcas.
  2. Pasar a ese campo lo que hoy diga el `field_tec_customer` de cada marca, y **retirar ese campo**.
  3. Retirar el `field_tec_customer` del producto y **recablear los cuatro sitios que lo leen**: la
     vista que crea las proformas del *Excel lover*, la pestaña Products de la ficha del cliente, la
     pantalla de *Organize products* —que pasa a ser por marca— y la columna con su filtro en las
     listas de productos.
  4. Ordenar las líneas de la proforma en dos niveles: primero por el orden de marcas del cliente,
     después por el orden de productos dentro de la marca. **Hoy las proformas no tienen ningún
     orden**, y esto es lo que se gana de verdad con el cambio.
  5. Borrar las filas de `draggableviews_structure` que están guardadas por cliente, que dejan de
     significar nada, y añadir al guardián que la marca de un producto esté en la lista de su cliente.

  **Lo de la mayúscula no es un detalle suelto:** mientras el producto siga con su cliente obligatorio,
  el botón *+ Product* de la pantalla del catálogo no puede rellenar la marca, porque el parámetro
  `?target_id=` que sabe leer el formulario está atado al campo de cliente. Eso se resuelve solo al
  hacer el paso 3.

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

- **Programar el cron.** **Lo único que queda es pegar una línea en el servidor el día del
  despliegue**, y está escrita y explicada en la sección 7, en "El cron del sistema". El lado de
  Drupal ya está como tiene que estar y se volvió a comprobar el 15 de agosto: `automated_cron` no
  está instalado, así que el cron no lo dispara la primera visita de la mañana; la clave de cron
  existe y la ruta `/cron/{key}` responde, que es la vía de repuesto si algún día no se puede usar
  `drush`; y los trece trabajos siguen como se dejaron el 14, con los dos peligrosos apagados. La
  prueba en local: una vuelta completa a mano tarda **3,4 segundos** con los ocho trabajos
  encendidos y **0,9 segundos** en régimen normal, y deja los dos avisos de cron del informe del
  estado en cero. Las dos decisiones que figuraban aquí se cayeron las dos: la del idioma no
  existía, porque `locale_cron` tiene la comprobación de traducciones puesta en "nunca" y no
  descarga nada; y la de los ficheros huérfanos tampoco bloqueaba, porque con
  `make_unused_managed_files_temporary` en `false` `file_cron` no los tocaba; se han borrado
  igualmente ese mismo día, por limpieza y no por el cron.
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

- ~~**Repasar las cuentas de usuario.**~~ **Hecho por el dueño el 15 de agosto de 2026**, a mano y
  desde el navegador. Quedan cinco cuentas contando el anónimo: `david`, que es la del dueño con rol
  de administrador, y las tres de los empleados, `lukpla`, `coo` y `manager`. Se fueron las cuatro
  que no eran de nadie de la empresa —`rat`, `david-antiguo`, `executive` y el `manager` viejo, dos
  de ellas con correo en `drusphere.com` y otra en `actafight.com`—. Neto, una cuenta menos, y el
  guardián del comprobador ya espera cinco.

  **Queda ponerles la contraseña a mano a las tres nuevas**, y con un detalle que hay que recordar:
  la de `manager` se creó **sin correo**, a propósito. Sin correo no puede recibir el enlace para
  ponerse contraseña ni ningún aviso del ERP, así que esa cuenta depende de que se la pongan por
  consola o desde la pantalla de administración. Las de `lukpla` y `coo` sí tienen dirección, en el
  dominio de la casa.

  Lo que sigue debajo es el estado en que quedó el repaso del 12 de agosto, que es de donde salió
  la lista de lo que había que limpiar:

  - ~~**El superusuario (`uid 1`) se llama `drusphere`**~~ — hecho el 12 de agosto. Se
    renombró a `david` y se le puso contraseña nueva. Para liberar ese nombre hubo que
    renombrar antes la cuenta bloqueada `David` (`uid 4`) a `david-antiguo`.
  - ~~**`devT` (`uid 7`) tiene rol de administrador**~~ — borrada el 14 de agosto de 2026. Antes se
    comprobó que no tenía absolutamente nada colgado, ni fichas ni ficheros, así que se fue sin
    dejar huérfanos. Llevaba sin entrar desde el 8 de junio de 2024.
  - ~~**Cuentas muertas por revisar y probablemente borrar**: `manager`, `executive` (la de
    Oscar, bloqueada el 12 de agosto), `rat` con correo en `actafight.com`, y una llamada
    `David` que es del dueño pero está bloqueada.~~ **Borradas las cuatro el 15 de agosto de
    2026.** Ninguna tenía rol de administrador —`manager` y `rat` eran *tec_manager*, `executive` y
    `david-antiguo` *tec_executive*—, o sea que no había ninguna llave maestra suelta, pero tampoco
    eran de nadie de la empresa.

### Los idiomas: qué hay montado, qué no, y por qué no hay nada que decidir todavía

Revisado el 14 de agosto de 2026, porque figuraba como decisión pendiente y estaba mal planteada.
Los empleados de la fábrica son tailandeses y laosianos y no hablan inglés, así que hace años se
pensó en una versión en tailandés por si algún día tenían usuario. Esto es lo que quedó de aquello.

**Drupal reparte los idiomas en cuatro módulos, y aquí están dos.** `language`, que permite definir
idiomas —hay dos, inglés por defecto y tailandés—, y `locale`, que traduce la **interfaz**, o sea los
textos que traen Drupal y sus módulos. Faltan los dos que harían lo que se tenía en la cabeza:

- **`content_translation` no está**: el contenido **no se puede traducir en absoluto**. Un material,
  un producto, un cliente o un pedido tienen un solo idioma y no hay dónde poner otro.
- **`config_translation` no está**: **no existe ninguna pantalla para traducir lo nuestro**. Los
  títulos de las vistas, las etiquetas de los campos, los botones, los estados de los pedidos: todo
  eso es configuración, y sin ese módulo no hay ni por dónde empezar a escribir el tailandés.

**Cómo se llegaría al tailandés hoy: solo por la dirección**, `/th/stock` en vez de `/stock`. Es la
única vía activada (`language.negotiation`, prefijos `en: ''` y `th: th`). Y esto es lo que más
importa: **la detección por usuario no está activada**, así que aunque se le cree la cuenta a un
empleado y se le ponga tailandés en el perfil, **entra y ve el ERP en inglés**. Tendría que escribir
el prefijo a mano o guardarse el enlace.

**Y lo que vería si entrara por ahí es poco y de lo que menos importa.** Al añadir el idioma, Drupal
se bajó las traducciones publicadas en drupal.org del núcleo y los módulos y las aplicó; de ahí salen
los 143 ficheros de `config/sync/language/th/`. Están a medias: en la pantalla de contenido salen en
tailandés "Título", "Tipo de contenido" y "Estado", y siguen en inglés "Author", "Updated",
"Operations", "Filter" y "Published". **Del ERP no hay traducida ni una palabra**: Stock Control,
Orders on Queue, Production Log, las cantidades, los estados y los botones de crear los escribimos
nosotros en inglés y no existen en tailandés en ningún sitio. Lo que está traducido a medias es la
fontanería de administración, que es justo lo que un operario no va a ver nunca.

Dicho en claro: **meter hoy a un tailandés por `/th/stock` le da la misma pantalla en inglés**, con
cuatro palabras del menú de administración cambiadas. Conviene no contarlo como "el ERP tiene versión
en tailandés", porque no la tiene, y quien se lo crea se lleva el chasco el primer día.

**Por eso no hay nada que decidir todavía, y se deja como está.** Lo que hay montado no es una
versión en tailandés, es el **andamio** para poder hacerla, y el andamio no cuesta nada: el trabajo de
cron no se dispara —tiene la comprobación en "nunca"— y los 143 ficheros viajan en cada despliegue
pero no hacen nada mientras nadie escriba `/th/`.

**El día que haga falta, el trabajo de verdad no es técnico.** Se instala `config_translation` y
**Lukpla misma puede teclear el tailandés** desde el navegador, pantalla por pantalla, sin que haga
falta tocar código. Y no hay que traducir el ERP entero: si los operarios solo van a usar Stock
Control y el registro de producción, son unas pocas docenas de etiquetas, medio día de trabajo suyo.
Necesitaría el permiso de traducir configuración, que hoy no tiene.

**Un aviso que cambia la cuenta si la respuesta fuera que sí**: el tailandés **no le sirve a un
laosiano**. Son idiomas distintos, con escrituras distintas, y Drupal los trata como dos idiomas
separados (`th` y `lo`). Si los laosianos de la fábrica no leen tailandés con soltura, harían falta
tres versiones y no dos, y eso ya no es medio día.

### Limpieza de datos y arreglo del importador

~~**Todo el contenido que hay dentro es de prueba y se borra entero.**~~ **Borrado el 14 de
agosto de 2026.** El detalle está abajo, en Hecho: 7.003 fichas y 892 términos fuera en
sesenta y siete segundos, y el ERP en verde después. El vocabulario de tipos de contacto se
respetó, que era el aviso que podía costar caro.

Del orden en que se hizo y de por qué, en Hecho. Aquí queda solo lo que sigue pendiente.

**El importador de materiales es ahora lo único que separa al ERP de tener catálogo.** Antes era
una mejora; desde el borrado es la puerta de entrada, porque no hay otra manera de meter 869
materiales. Existe (`tec_inventory_csv_importer`) y por él entraron 470 de los de prueba, pero
estaba a medio hacer.

**Arreglado el 15 de agosto de 2026.** Pasa de **3 a 20 columnas con destino**, con las **nueve
obligatorias** cubiertas y ninguna columna pidiéndose dos veces. `Traceable` pasa a `Traceability`,
el proveedor va a `field_tec_vendor` resolviendo por título, el límite sube de 100 a **1.000 filas
por pasada**, y la creación automática de unidades y tipos queda **apagada**. Los seis términos
duplicados, el `Blue ` con espacio incluido, ya no están: se limpiaron en una fase anterior.

Se probó importando de verdad, no solo validando: tres materiales creados con los veinte campos
rellenos y el proveedor enganchado al CRM, más una **fila trampa** con la unidad `Metro Inventado`,
que se rechazó sin crear nada — los recuentos de unidades, tipos y colores salieron idénticos antes
y después, o sea que la autocreación está apagada de verdad. Todo lo de la prueba quedó borrado.

La plantilla está en `docs/plantillas/`: el `.xlsx` es para rellenar (obligatorias en rojo,
opcionales en gris, la ayuda en el comentario de cada celda, y cuatro hojas con las listas cerradas
que existen hoy) y el `.csv` es el que se sube, porque el lector solo acepta `txt csv tsv xml opml`
y un `.xlsx` no se puede ni subir. Las columnas no están escritas a mano: **el generador las lee del
propio importador**, así que la plantilla no puede desincronizarse. Era justo el fallo de `Traceable`
pidiendo una columna que el fichero llamaba de otra manera.

**Y una trampa que hay que saber antes de lanzar las 857 filas: la importación corre con el usuario
que firma la ficha, no con quien pulsa el botón.** Feeds hace `switchTo($feed->getOwnerId())` antes
de empezar. Importa porque la columna `Supplier` resuelve contra la vista `tec_crm_references`, que
tiene el acceso limitado a los roles `tec_supervisor`, `tec_manager`, `tec_executive` y
`administrator`, y **el usuario 1 no se salta ese control**: el control de acceso por rol de Views
compara roles y no hace excepción con el uno. La ficha 4 la firma `david`, que es administrador, así
que la importación funcionará. Si alguien le cambia el autor a una cuenta sin esos roles, fallan las
857 filas con "This entity cannot be referenced", y poner `--user` en la línea de órdenes no lo
arregla. Queda escrito también en la hoja de instrucciones del Excel.

Sigue pendiente **la decisión del dueño** sobre el proveedor vacío en el fichero, que está más abajo
en esta misma lista y no es técnica.

El diagnóstico que se encontró, punto por punto:

- **Conecta tres campos, no cinco.** Esto se leyó en la configuración el 14 de agosto por la
  noche, y es peor de lo que decía aquí: `feeds.feed_type.tec_inventory_csv_importer` declara
  **quince columnas de origen y solo tres llegan a un destino** — nombre, descripción y
  trazabilidad. Todo lo demás tiene origen y no tiene destino. No es que falle: es que no está
  enchufado, y por eso los campos entran vacíos.
- **Y de esas tres, una tampoco funciona.** La trazabilidad lee una columna llamada
  `Traceable`, y en los ficheros de verdad la columna se llama `Traceability`. Nunca encaja, así
  que entra vacía en silencio.
- **Las columnas duplicadas tienen explicación.** Hay orígenes con espacios y con guion bajo
  para lo mismo: `Material Type` y `Material_type`, `Unit of Use` y `Unit_of_use`. Es la huella
  de alguien reintentando contra ficheros cuyas cabeceras cambiaron por el camino. Los ficheros
  reales usan guion bajo, así que **los orígenes con espacios no encajan con nada**.
- **El proveedor no se enlaza nunca, y hay algo peor.** El origen declarado lee `Suppliers` y en
  el fichero la columna es `Supplier`, en singular. Pero además, en los dos tramos que se
  miraron del listado de 857 materias, **la columna de proveedor viene vacía**, igual que
  `Price ZZ` y `Moq`. Y eso choca de frente con la regla de `docs/material_inventory_naming_matrix.md`,
  que dice que el proveedor es obligatorio y debe **bloquear la inserción**. Las dos cosas no
  pueden ser verdad sobre ese fichero: o el proveedor se rellena en el Excel antes de importar,
  o la obligatoriedad vale para la pantalla y no para la importación. Es una decisión del dueño,
  no técnica.
- ~~**Hay dos campos de proveedor y hay que elegir cuál es el bueno.**~~ **Resuelto el 14 de
  agosto, y lo resolvieron los datos.** Al sacar los materiales a CSV antes de borrarlos se
  contó cuántos traían cada campo relleno: `field_tec_vendor` lo tenían **38 materiales** y
  `field_tec_suppliers` **ninguno, cero de 869**. O sea que el campo que se usaba de verdad es
  `field_tec_vendor`, el obligatorio que se elige de una lista, y el otro se puede retirar. La
  decisión se llevaba semanas en el aire y se contestó sola en cuanto hubo con qué contar.

  **Y `field_tec_suppliers` está retirado del todo desde el 15 de agosto de 2026**: columna,
  relación y entradas de tabla fuera de las cuatro pantallas de `views.view.tec_inventory`, fuera de
  las pantallas de ver y editar del material, y fuera el campo, el almacén y la tabla
  `taxonomy_term__field_tec_suppliers`, que Drupal tiró en el momento al no haber ni un dato. Un
  barrido de toda la configuración activa dice que nadie lo nombra ya. La copia de la vista, 5.377
  líneas, está en `backups/views.view.tec_inventory.backup-2026-08-15.yml`.

  De paso se arregló **el botón "Add new material" de la pestaña del proveedor**, que llevaba
  quién sabe cuánto abriendo el formulario sin proveedor puesto. El fallo no era el campo viejo: la
  cabecera de `block_2` escribía `{{ id }}`, que es una marca de columna de fila y **una cabecera de
  vista no la ve**, así que el enlace salía con el hueco vacío. Ahora usa `{{ raw_arguments.id }}` y
  `/supplier/31` da `?target_id=31&destination=/tec_crm/31`, que abre el formulario con
  `<option value="31" selected>`. Se eligió `raw_arguments` y no `arguments` porque `arguments.id` es
  el *título* del argumento: hoy valen lo mismo, pero el día que alguien encienda un validador o un
  título pasaría a valer la etiqueta de la ficha y el enlace se rompería por dentro sin dejar de
  parecer correcto. El `destination` se dejó en el camino interno `/tec_crm/{id}` y no en el alias,
  porque el camino lo garantiza la ruta y se cura solo si pathauto cambia de patrón. Comprobado
  además que el relleno funciona con las cuatro cuentas que pueden crear materiales, no solo con la
  del dueño. Queda el arnés `scripts/carga-el-listado-de-materiales.php`, que dibuja el listado de
  materiales y sirve para ver si la pantalla más usada se descoloca.
- **Procesa solo 100 filas por pasada**, así que con 869 materiales hay que lanzarlo nueve
  veces o subir ese límite.
- **Inventa unidades y tipos de material** cuando el texto del Excel no coincide exactamente.
  Escribir "metros" donde el sistema tiene "metro" no da error: crea una unidad nueva. Como
  las 13 unidades y los 23 tipos son correctos, conviene apagarlo para que una errata falle
  en voz alta en lugar de ensuciar el catálogo. Que esto pasaba de verdad ya no es una
  sospecha: el borrado se encontró **seis términos duplicados** creados así, entre ellos un
  color "Blue " con un espacio detrás y un tipo de material repetido.
- Arrastra además una docena de columnas declaradas y sin usar, restos de pruebas, que
  enredan a la hora de entender qué espera el fichero.

El trabajo es: mapear los campos que faltan, conectar el proveedor a `field_tec_vendor`, limpiar
las columnas sobrantes y generar la plantilla de Excel con una columna por dato y los nombres
exactos que el importador espera.

**Pero antes que todo eso hay un bloqueo, y cambia el diagnóstico de esta sección.** El importador
no está a medio hacer: **está bloqueado**. Tal como está hoy la configuración no puede crear ni una
ficha de material, y no por los campos sin mapear, sino por tres campos obligatorios que ni se ven
ni se pueden rellenar. El detalle y el porqué están más abajo, en la sección del sistema de unidades
opcionales de Óscar.

**El importador de BoM está peor, y en un punto concreto.** `tec_bom_items_importer` recibe
ficheros de tres columnas —`ID, Material, Requires`—, uno por producto y talla. Tiene orígenes
declarados para `Material` y para `Requires` y **ninguno de los dos está conectado**: el nombre del
material acaba solo dentro del título que se genera con una regla de `feeds_tamper`
(`[material1]-[parent:imported]`), y **la cantidad se pierde**. O sea que importa BoM sin
material y sin cantidad. Trae además dos trampas: `update_non_existent: _delete`, con lo que toda
fila que falte en el fichero borra su elemento, y la clave única es la columna `ID`, que en los
ficheros de Primo **venía vacía en todas las filas** — con clave única vacía, las veinticuatro filas
colapsan sobre un solo elemento. Eso explica los reintentos que había guardados
(`primo-black-s.csv`, `_1`, `_2`).

~~**Dos de los tres importadores están huérfanos, y abrirlos revienta.**~~ **Borradas las dos fichas
el 15 de agosto de 2026**, por consulta directa a `feeds_feed`, que era la única vía: la normal de
Drupal pasa por pedir el tipo y lanza antes de llegar. El recuento `importaciones` del guardián baja
de 3 a 1.

Las fichas 5 y 6 —"Primo products" y "Trust products"— eran de tipo `tec_products_csv_importer`, un
importador de productos cuya configuración ya no existe, y confirmaban que sí se intentó importar
productos en su día y se quedó a medias. **Y era peor de lo que estaba apuntado aquí**: no es que
`/feed/5/edit` reventara, es que reventaba la lista entera. `FeedListBuilder::buildRow()` se cae al
pedir la etiqueta del tipo, así que `/admin/content/feed` no se podía ni abrir y el ERP se quedaba
sin la pantalla de importaciones por dos fichas muertas.

No se perdió nada, y se comprobó antes de borrar: la tabla `tec_product__feeds_item` **no ha
existido nunca** en ningún volcado, así que ningún importador ha escrito jamás un producto del
sistema nuevo. La pista de `tec_gui__feeds_item` se confirma a medias, porque la tabla sí existía
pero vacía en los tres volcados que hay. Y sus ficheros de origen, que siguen en el disco, traen
columnas de producto acabado —`Product Name`, `Picture`, `Pattern`, `Sales price`, `Brand`,
`Category`, `Size`, `Customer`—, nada que ver con el inventario. Eran cáscaras vacías.

**El importador de productos, con sus variaciones y sus tallas: pendiente, y no ahora.** Decisión del
dueño del 14 de agosto. Lo que sí queda dicho es por dónde **no** hay que ir, porque está en la
configuración: `field.field.tec_product.tec_size_variation.field_tec_import_bom` es una referencia a
`tec_bom_items_importer` con `auto_create: true`, o sea **un importador y una subida de fichero por
cada variación de talla**. Para cincuenta productos con tres colores y cuatro tallas son seiscientos
importadores y seiscientos CSV a mano. No es que esté roto: es que el diseño no escala, y es la
misma huella de las fichas 5 y 6 de arriba, el intento que se quedó a medias.

Cuando se retome, la forma correcta es un cargador propio que construya el árbol entero de una
pasada —producto, variaciones de color, variaciones de talla y BoM—, con modo de prueba en
seco antes de escribir, porque son del orden de trescientas fichas por producto y referencias en los
dos sentidos que hay que dejar cuadradas. Y antes de escribir una línea, comprobar si **duplicar
producto ya resuelve el caso**: los dos procesos de ECA que duplican (`process_llpx4tp` y
`process_icpsbgv`) construyen el árbol completo, así que puede que meter un producto patrón a mano y
duplicarlo salga más barato que un importador.

~~**Se arregla antes del borrado, no después.**~~ Se hizo al revés, por decisión del dueño del
14 de agosto, y el motivo era bueno: así el servidor recibe una base limpia de un solo empujón
en vez de recibir la sucia y limpiarla después. El precio de esa decisión es que ahora mismo el
ERP está vacío y sin manera de rellenarlo, así que el importador pasa a ser urgente.

**Y el banco de pruebas está guardado.** Antes de borrar, los 869 materiales salieron a
`C:\laragon\backups\materiales-antes-del-borrado\materiales.csv`, con las 57 columnas y todos
los valores, las referencias con su etiqueta legible delante del identificador. Sirve para dos
cosas: ensayar el importador arreglado contra datos reales, y saber qué aspecto tienen de verdad
los datos que tendrá que tragar. Sale fuera del proyecto a propósito, que lleva costes y
proveedores dentro y eso no va a GitHub.

De ese CSV sale además el mapa de por dónde empezar. **Solo ocho campos estaban rellenos en los
869 materiales**: tipo de material, si se fracciona, nivel de stock, trazabilidad, unidades,
control de unidad de uso y la unidad de uso (860 de 869). A media tabla, la mitad del catálogo:
unidades de paquete y de compra 52%, color 50%. Y a partir de ahí, casi nada — **precio 50
materiales, coste 15, plazo de entrega 3, punto de pedido 2, SKU interno ninguno**. Los cinco
campos `placeholder` y `field_tec_importer_item_id` están vacíos del todo, así que sobran. Esto
confirma lo que se sospechaba y lo cuantifica: el catálogo de prueba no estaba mal, estaba a
medio rellenar, y lo que faltaba era justo lo que el importador no sabe meter.

### El sistema de unidades opcionales de Óscar: qué queda, y por qué bloquea el importador

Auditoría del 14 de agosto por la noche, pedida por el dueño, con una decisión suya que la ordena
toda: **todos los materiales tienen tres unidades de medida y dos factores de conversión, sin
excepciones**. Si una unidad no cambia respecto de la anterior, el factor es 1. La idea de que las
unidades y los factores son opcionales según unas casillas queda erradicada del ERP. No es una
mejora sobre el sistema de Óscar: es sustituirlo, porque era una complicación innecesaria.

**Lo que ya está hecho está bien.** Los cinco campos canónicos son obligatorios y llevan los nombres
nuevos:

| Campo | Etiqueta | |
| :-- | :-- | :-- |
| `field_tec_unit_purchase` | Purchase UoM | obligatorio |
| `field_tec_uos` | Inventory UoM | obligatorio |
| `field_tec_unit_use` | Consumption UoM | obligatorio |
| `field_tec_units` | Purchase to Inventory Factor | obligatorio |
| `field_tec_split_into` | Inventory to Consumption Factor | obligatorio |

Y con ellos son obligatorios, con razón, `field_tec_vendor` (Supplier), `field_tec_cost` (Purchase
Cost (No VAT)) y `field_tec_material_type`. Ocho obligatorios legítimos. **Fuera del formulario no se
ha desmontado nada**, y ahí está todo el problema.

#### El hallazgo que bloquea el importador

Cuatro campos del sistema viejo siguen marcados como **obligatorios y escondidos del formulario**, y
tres de ellos no tienen valor por defecto:

| Campo | Etiqueta | Defecto |
| :-- | :-- | :-- |
| `field_tec_packaging` | Unit of Packaging (UoPackaging) | ninguno |
| `field_tec_uos_uou` | 1 UoS = UoU | ninguno |
| `field_tec_uou_split_qty` | UoP to UoS conversion formula | ninguno |
| `field_tec_uou_control` | Unit of use | `same_as_uop` |

Por pantalla esto no se nota, porque el formulario de Drupal descarta las violaciones de los campos
que no muestra. Pero el importador guarda por código y ahí se valida la ficha entera. Está
comprobado en el código de Feeds, no es suposición: `EntityProcessorBase::entityValidate()` llama a
`$entity->validate()` y solo filtra por **tipo** de restricción, con `skip_validation_types` vacío y
`skip_validation: false` en `tec_inventory_csv_importer`
(`modules/contrib/feeds/src/Feeds/Processor/EntityProcessorBase.php`, líneas 763-776).

Conclusión: **hoy el importador no puede crear ni un material**, fallaría en esos tres obligatorios
que no hay manera de rellenar desde ninguna pantalla, más el proveedor. Quitarles el obligatorio son
tres líneas de configuración y es el paso más barato de todo el desmontaje.

Y de paso explica el valor por defecto de `field_tec_uou_control`, que es `same_as_uop`: es la idea de
Óscar escrita en la configuración, la unidad de uso igual a la de compra salvo que alguien diga lo
contrario.

#### Los 22 campos que quedan

Todos escondidos del formulario, todos vivos en la base de datos, todos sobre `taxonomy_term` en el
vocabulario `tec_inventory`.

**Los cuatro interruptores de opcionalidad**, el corazón del sistema: `field_tec_split_inventory`
("Convert inventory"), `field_tec_package_units` ("Convert"), `field_tec_stock_unit_check`
("Custom UoS") y `field_tec_moq_package` ("Set Packaging unit as MoQ").

**La cadena de embalaje**, que es una cuarta unidad de medida paralela a las tres buenas:
`field_tec_packaging` ("Unit of Packaging"), `field_tec_split_unit` ("Packaging unit"),
`field_tec_price_by_package` ("Price by package"), `field_tec_purchasing_moq` ("Purchasing MoQ") y
`field_tec_moq_unit` ("MoQ unit").

**La matriz de conversión por pares**, que es lo que explica por qué esto se le fue de las manos: en
vez de dos factores, un campo por cada par ordenado de unidades, con las variantes de embalaje
incluidas. Once campos: `field_tec_uop_uou` ("1 UoP = UoU"), `field_tec_uos_uop` ("1 UoP = UoS"),
`field_tec_uos_uou` ("1 UoS = UoU"), `field_tec_uou_uop` ("1 UoU = UoP"), `field_tec_uou_uos`
("1 UoU = UoS"), `field_tec_uop_pu_uou` ("1 UoP PU = UoU"), `field_tec_uou_uop_pu`
("1 UoU = UoP packaging units"), `field_tec_uos_pu_uop` ("1 UoS PU = UoP"), `field_tec_uos_pu_uou`
("1 UoS PU = UoU"), `field_1_uou_uos_pu` ("1 UoU = UoS packaging units") y `field_uop_to_uos`
("UoP to UoS conversion formula"). Con cuatro unidades los pares ordenados son doce; están casi
todos.

**Y dos duplicados con nombre tramposo**, que conviene mirar antes de borrar por si algo los usa:
`field_tec_uou_split_qty`, un entero cuya etiqueta es "UoP to UoS conversion formula", o sea un
duplicado de `field_tec_units`; y `field_tec_uou_control`, el desplegable con el defecto
`same_as_uop`.

Cuatro más cinco más once más dos, 22 campos con sus 22 almacenamientos.

#### Los cuatro procesos de ECA

**`process_rxuimsq`, "TEC Inventory: Calculation data" — activo**, con su clon `process_uix9n5i`
apagado. Se dispara en `content_entity:insert` y `:update` de `taxonomy_term tec_inventory`, o sea
**también cuando guarda el importador**. Lo que hace, con los interruptores vacíos, que es como
llegan siempre desde que se escondieron del formulario: si `field_tec_split_inventory` no vale 1,
copia `field_tec_unit_purchase` en `field_tec_unit_use`; y si `field_tec_package_units` no vale 1,
copia `field_tec_unit_purchase` en `field_tec_uos`. Las dos con `method: set:clear`, o sea
**sobrescribiendo lo que hubiera**. En claro: **aplasta la unidad de stock y la de uso a la de
compra, en silencio, en cada guardado**. La rama contraria, cuando la casilla de embalaje sí vale 1,
pone la unidad de stock desde `field_tec_packaging` y copia `field_tec_units` sobre
`field_tec_split_into`, mezclando los dos factores.

Esto es lo primero que hay que desmontar, y **antes de importar nada**: si entran los 857 materiales
con el proceso en pie, entran todos con las tres unidades iguales a la de compra y los factores
puestos, o sea materiales que dicen comprarse y consumirse en rollos con un factor de 100 dentro. Un
daño que no da error y se descubre meses después en un coste raro.

**`process_fpvka81`, "TEC Line item: Set PO line item data" — activo**, con dos clones apagados
(`process_pehrnr8`, `process_idtd6ah`). Ramifica sobre los interruptores del material: si
`field_tec_split_inventory` vale 1, multiplica el total de la línea por `field_tec_uou_split_qty`; si
`field_tec_package_units` vale 1, recalcula el precio como coste de línea por `field_tec_units`
("Custom UoP found" en el registro). Con los interruptores siempre vacíos **las dos ramas están
muertas**, así que hoy el total de una línea de pedido de compra es cantidad × coste de compra del
material, sin conversión ninguna. Es la explicación de la sospecha sobre los costes: si el BoM
consume en unidades de uso y se aplica el coste del rollo entero, el coste de producción está
inflado por el factor de conversión. Para eso existe "Consumption Cost", que **sí lo calcula alguien,
pero solo dentro del navegador**. Ver más abajo, en el paso 5: lo hace un JavaScript del formulario, así
que el importador va a dejar ese coste vacío.

#### Las cuatro vistas, que es el trabajo de verdad

**`tec_order_material_calculation_terms` y `tec_order_material_calculation_summary`** están
construidas enteras sobre la matriz. Llevan cuatro fórmulas de `views_simple_math_field` —
`@field_tec_quantity * @field_tec_uou_uos`, `@field_tec_quantity / @field_tec_uos_pu_uou`,
`@field_tec_quantity / @field_tec_uop_pu_uou` y `@field_tec_uou_uop * @field_tec_quantity` — y
campos de `views_conditional` que eligen entre ellas según `field_tec_split_inventory` y
`field_tec_package_units`. Es la pantalla que traduce lo que se vende en materiales que hacen falta,
o sea el cálculo que alimenta las compras. No tienen ruta propia: van embebidas.

**`tec_order_sales_order_line_items`** usa `field_tec_package_units`, `field_tec_packaging` y
`field_tec_price_by_package` con condicionales del tipo "si Convert está On, muestra la unidad de
embalaje". Y esta sí sirve pantallas vivas: `po/draft/%`, `o/draft/%`, `po/%/print` y `o/pf/%/print`.
Con los interruptores apagados esas columnas salen por el ramal falso.

**`tec_inventory` y `tec_inventory_elements`** también nombran alguno de los 22, sin haber
comprobado en qué columna exactamente. Hay que mirarlo antes de borrar campos.

Por eso el orden importa: borrar un campo que una vista referencia deja manejadores roto en pantallas
que están en uso.

#### El formulario ya está limpio

Las reglas de campos condicionales del material están vacías (`conditional_fields: {  }` en
`core.entity_form_display.taxonomy_term.tec_inventory.default`). Las únicas que quedan vivas en el
ERP son las de líneas de pedido, sobre `field_tec_custom_sales_price`, y no tienen que ver con esto.
Esa parte del desmontaje ya estaba hecha.

#### El orden de desmontaje, en cinco pasos

1. **ECA.** Borrar `process_rxuimsq` y su clon `uix9n5i`. En `fpvka81`, quitar las dos ramas de los
   interruptores y el multiplicador, y dejar el coste de la línea calculado con los dos factores
   buenos. Va antes del importador.
2. **La validación.** Quitar el obligatorio a `field_tec_packaging`, `field_tec_uos_uou` y
   `field_tec_uou_split_qty`. Tres líneas, y con eso el importador pasa de imposible a posible.
3. **Las dos vistas de cálculo de material.** Rehacer las fórmulas sobre `field_tec_units` y
   `field_tec_split_into`. Es la pieza grande y la única que necesita pensar, porque hay que decidir
   la fórmula única de conversión. **Va después de tener catálogo**: sin materiales ni pedidos no hay
   manera de comprobar que la fórmula nueva da bien.
4. **Limpiar `tec_order_sales_order_line_items`** de las tres columnas de embalaje y sus
   condicionales, y revisar `tec_inventory` y `tec_inventory_elements`.
5. **Borrar los 22 campos** con sus almacenamientos, cuando ya no los referencie nadie.

**Ejecutado el 14 de agosto por la noche**, por decisión del dueño de acabarlo del tirón. Los cuatro
primeros pasos están hechos y comprobados; queda el quinto. Lo que se aprendió al hacerlo corrige
tres cosas de lo que está escrito más arriba, y las tres importan:

**Uno: el fallo de los pedidos de compra no era el que se creía.** El registro dio la traza literal,
`round('10 * 80.00', 2)`, así que sí había una acción escribiendo la multiplicación sin hacer. Pero al
probarlo salió un segundo fallo que nadie había visto: el cálculo del total multiplicaba la cantidad
por `field_tec_cost` **de la línea**, y ese campo solo existe en el material. El total salía cero y no
se guardaba nunca. Lo que se veía en pantalla venía del formulario, no del cálculo. Corregido a
`[inventoryItem:field_tec_cost:value]`, con el proceso pasado a `presave` para calcular antes de
guardar en vez de guardar dentro del guardado, con lo que desaparece además el aviso de recursión que
ECA escribía como error en cada línea.

**Dos: eran cuatro campos obligatorios, no tres.** Faltaba `field_tec_uou_control`. Y la razón de que
por pantalla no se notara está ahora entendida del todo: Drupal descarta a propósito las violaciones
de los campos que no muestra, pero Feeds valida la entidad entera.

**Tres: el display que se dibuja de las dos vistas de cálculo ya estaba bien.** Las cuarenta columnas
atadas a la matriz de pares estaban en el display maestro, que no se dibuja nunca solo, y en
condicionales **ocultas** del bloque. Las columnas que de verdad ve produccion ya calculaban con
`cantidad / field_tec_split_into` y `cantidad / field_tec_split_into / field_tec_units`, y ya enseñaban
el paréntesis con el factor siempre puesto. Así que el paso 3 no fue decidir fórmulas nuevas: fue
tirar peso muerto y rehacer el maestro a imagen del bloque.

**Tres decisiones del dueño** que ordenaron el paso 4: el paréntesis con el factor se enseña siempre
(antes dependía de la casilla); los displays maestros se rehacen como el bloque en vez de vaciarlos; y
el pedido mínimo se queda siempre en unidades de compra.

##### Las dos trampas de Views que hay que conocer para no repetirlas

Ninguna de las dos da error. Las dos dejan la página cargando con un dato menos, que es peor.

**Una columna solo ve el valor de las columnas que van antes que ella.** Las condicionales viejas
estaban al final de la lista de campos y por eso lo veían todo. Al escribir la rama buena en una
columna que estaba antes, el paréntesis salía vacío: `Roll ( )`. Se arregla moviendo la columna
detrás de los campos que lee. Pasó en cinco displays. De paso, la misma comprobación descubrió un
fallo viejo que no tiene nada que ver: en el resumen de materiales, la columna del proveedor pone un
enlace a la ficha del contacto con una dirección que trae una columna posterior, así que **el enlace
del proveedor estaba muerto desde siempre**. Arreglado adelantando la columna oculta, sin tocar el
orden de lo que se ve.

**Una columna calculada lee el valor de todas las columnas que tenga marcadas, aunque no salgan en la
fórmula.** Marcar una columna que no existe tumba la página entera con
`Call to a member function getValue() on null`. Había seis marcas así, y una de ellas rompía el
pedido de venta. Y al revés: si la fórmula nombra una columna que no está marcada, el módulo deja la
arroba escrita y la librería de cálculo se queja de carácter ilegal. Las dos direcciones hay que
cuidarlas.

Los dos guiones que quedan de esto sirven para la próxima vez: `donde-vive-el-sistema-de-oscar.php`
dice qué configuración nombra cada campo y en calidad de qué, y `sacar-a-oscar-de-las-vistas.php`
lleva el plan escrito, compara los cabos sueltos de antes y de después, y **no guarda nada si el
cambio rompe algo**.

##### Lo que se comprobó antes de dar el paso 4 por bueno

- Los 13 displays tocados **dibujados de verdad**, no solo pedidos por su dirección: una página
  devuelve 200 aunque una columna salga vacía. `dibujar-los-displays-tocados.php`.
- Los números, a mano: pedido de compra 758, 10 × 80 = 800. Cálculo del pedido 760, 30 piezas × 2,5
  cm = 75 cm; 75 / 10000 = 0,0075 rollos; 0,0075 / 0,40 = 0,01875 metros; coste 75 × 0,02 = 1,50.
- Las cuatro piezas del listado de materiales: `meters (0.40 roll)`, `Rolls (10000.00 cm)`,
  `Centimeter (cm) ฿ 0.02/cm` y, poniéndole un mínimo al material, `5 Meter (m)`.
- La prueba de humo entera: 44 pantallas, ninguna rota.

**Y una mejora que no se buscaba: las pantallas van mucho más rápido.** Cada columna muerta era una
unión más en la consulta y un campo más que renderizar. La exportación de materiales a hoja de
cálculo pasó de 4.650 ms a 100, el PDF de un pedido de compra de 4.660 a 195, el editor de líneas de
2.754 a 249 y la ficha de un pedido de 1.006 a 247.

**El factor con el símbolo de la unidad equivocada, arreglado.** Se apuntó aquí como pendiente y se
hizo esa misma noche, después de los campos. Un factor solo significa algo si al lado va la unidad de
destino, y en dos columnas iba otra: en el listado de materiales, `Meter (m) (0.40 m)`, que se lee "un
metro son 0,40 metros"; y en la vista de cálculo, `Meter (m) (0.40 cm)`. Las dos tenían que decir
`(0.40 roll)`.

La causa: para escribir el símbolo de una unidad, la vista necesita una **relación** hasta el término
de esa unidad, y ninguna de las dos vistas la tenía con la unidad de inventario — sí con la de compra y
con la de consumo. Así que quien escribió el paréntesis puso el símbolo que tenía a mano. No eligió
mal: no había ninguno correcto disponible.

El censo se hizo con `que-simbolo-lleva-cada-factor.php`, que recorre todas las vistas, busca las
reescrituras que juntan un factor con un símbolo, y **mira de qué relación cuelga ese símbolo**, que es
lo que decide qué unidad sale. Eran **seis paréntesis: cuatro bien y dos mal**, los dos en el display
que se dibuja. Las cuatro piezas de la ficha del material ya estaban bien, porque ahí cada bloque sí
tenía la relación buena.

El arreglo, por vista: crear la relación a `field_tec_uos` en el maestro clonando una que ya funciona,
colgar de ella una columna de símbolo escondida, **moverla delante de la columna que la lee** —la
trampa del orden, otra vez— y cambiar el token. La relación va en el maestro porque el display que la
necesita hereda de él; darle lista propia lo dejaría descolgado de los cambios futuros, a cambio de
ahorrar una unión indexada por término, que al lado de las cuarenta columnas que se quitaron no se
nota. Comprobado: el censo da seis de seis y la pantalla pone `Meter (m) (0.40 roll)`.

##### Cinco displays con el estilo a medias, y un aviso que llevaba tiempo sonando

Al guardar las vistas volvió a salir `Undefined array key "type"`, y la primera vez fue culpa del
guion: en PHP basta con **nombrar por referencia una ruta que no existe** para que se cree vacía, así
que al ir a mirar `style.options` de un display que no tiene estilo propio, se le creaba un `style` sin
`type`. Corregido leyendo la ruta con `??` en vez de tomar referencia.

Pero al ir a limpiarlo aparecieron **cinco displays así, y tres no eran de este trabajo**: los tres
bloques de `tec_orders_orders`, que lo arrastran desde el commit `8b8ed384`, el de control de stock y
separación de estados de pedido. O sea que ese aviso llevaba saliendo en cada guardado de esa vista
desde entonces. Los cinco decían seguir al maestro, así que la reparación es quitarles el `style` vacío
y dejarlos heredar, que es lo que hacían de verdad. Ahora se guardan sin un solo aviso.

Merece la pena por lo mismo que la comprobación: un aviso que salta siempre enseña a no mirar los
avisos.

#### Los dos costes derivados pasan al servidor, y el de consumo a seis decimales

Hasta ahora Inventory Cost y Consumption Cost los calculaba un JavaScript del formulario del material.
Funcionaba, pero solo existía **si había un navegador con el formulario abierto**: todo material creado
por código —el importador que viene, un duplicado de ECA, un guion— se guardaba con los dos vacíos. Y
son los dos números por los que dividen todas las vistas de coste. Se vio al crear el material de
prueba por código en la fase 2: los dos en blanco.

**Ahora los calcula el servidor**, en `tec_inventory_taxonomy_term_presave()`. Se eligió un gancho y no
ECA por una razón que ya estaba escrita en ese mismo fichero, en el comentario del título de las
variaciones de color: `presave` cubre todos los caminos —formulario, formularios embebidos, duplicados
de ECA y guiones— sin guardados extra ni recursión. Y la recursión es exactamente lo que obligó a pasar
`fpvka81` a `presave` la noche anterior. El JavaScript se queda, pero solo para lo que necesita un
navegador: que los dos números se muevan mientras se teclea el coste de compra.

**Y el campo de consumo pasa a 12 cifras con 6 decimales**, porque con dos ya estaba en el límite: el
material de prueba guarda 0,02 y uno un poco más barato daría 0,008, que se guardaba como 0,01, un 25%
de error en todos los BoM.

##### La puerta que Drupal deja para esto no sirve para campos configurables

Drupal no deja cambiar los decimales de un campo que ya tiene datos, y tiene una marca para saltárselo,
`column_changes_handled`. No sirve aquí: `FieldStorageConfig::preSave()`, en la línea 331, **filtra los
ajustes que no conoce antes de que nadie mire la marca**. Se probó, la columna cambió y el guardado de la
configuración no pasó, dejando la base diciendo 12,6 y la configuración diciendo 10,2. Esa marca es para
campos base, no para estos.

Lo que sí está contemplado es el camino de un campo **sin datos**: el núcleo tira las tablas, las vuelve
a crear con los decimales nuevos y deja apuntado el cambio. Que no es un sitio, son **tres**:
`field.storage.…`, `entity.storage_schema.sql` y `entity.definitions.installed`, y este último sí guarda
copia de los campos configurables, contra lo que cabría suponer. Escribir los tres a mano es pedir una
inconsistencia, así que se aparta el contenido, se le deja hacer su trabajo y se devuelve el contenido.

El aparte son **tablas de copia, no memoria**: MySQL no sabe deshacer un `DROP TABLE`, así que si algo se
rompe por el camino los precios tienen que seguir estando en algún sitio. La actualización cuenta las
filas antes y después, y si no cuadran **deja las copias donde están** y lo dice. Vive en
`tec_inventory.install`, así que en producción entra por el despliegue como cualquier otra actualización.
Comprobado: los cuatro sitios dicen 12,6, ninguna tabla de copia se quedó atrás y el precio sobrevivió.

##### El step que Drupal escribe como 1.0E-6

El JavaScript saca los decimales del atributo `step`, que es donde Drupal apunta lo que aguanta la
columna, para no quedarse escribiendo dos cuando la columna admite seis el día que alguien cambie la
escala. La primera versión los contaba como caracteres detrás del punto y daba **cuatro**: para seis
decimales Drupal escribe `step="1.0E-6"`. Lo cazó la propia comprobación al imprimirlo. Hay que leerlo
como número.

##### Y lo que de verdad se estaba perdiendo: la vista redondeaba antes de multiplicar

Guardar seis decimales no sirve de nada si la vista los tira antes de usarlos, así que se auditaron las
**veinte fórmulas** de todas las vistas con `donde-redondean-las-formulas.php`, mirando con cuántos
decimales se dibuja cada dato del que se alimentan. El módulo de cálculo no lee la base: **lee lo que la
columna dibuja**.

Y ahí estaba: el total de material, `@field_tec_quantity * @field_tec_price`, leía el coste dibujado con
dos decimales. O sea que un material a 0,001 entraba en la fórmula como 0,00 y **el ERP decía que la tela
era gratis**. La fórmula ya redondea su propio resultado a dos decimales con el símbolo del baht, que es
donde hay que redondear el dinero: al final, no antes de multiplicar.

Arreglado en las dos vistas de cálculo, y sin efecto en pantalla porque esa columna está escondida:
existe solo para alimentar la fórmula. Demostrado bajando a propósito el coste de compra del material de
prueba hasta que el de consumo queda en 0,001: el total pasa de los ฿ 0,00 que habría dado antes a
**฿ 0,08**, que es 75 × 0,001 redondeado. Y el coste vuelve a su sitio al acabar.

Los otros dos sitios donde una fórmula multiplica dinero con dos decimales son `@field_tec_cost`, el
coste de compra, y ahí está bien: ese se teclea, y con dos decimales es exacto.

**Queda una decisión pequeña, apuntada y no hecha:** Inventory Cost sigue guardando dos decimales. No
arrastra error a Consumption Cost, porque el cálculo divide el coste de compra por el primer factor y usa
ese resultado **sin redondear** para el segundo. Lo único que produce es que los dos números de la ficha
no se reconcilien a mano cuando la primera división no es exacta: 100 entre 3 se ensena como 33,33 y el
de consumo sale de 33,3333… Si algún día molesta, es la misma actualización con otro campo.

##### Paso 5: los 23 campos, borrados

Hecho la misma noche, después de la comprobación. **Los 23 campos, sus 23 almacenamientos y sus tablas
de datos ya no existen**, y el barrido de configuración da cero por los tres lados: cero manejadores
de vista, cero definiciones de campo, cero ficheros que los nombren. En la base solo queda
`taxonomy_term__field_tec_uos`, que es la unidad de inventario buena. Se llevaron por delante los
valores que había, que eran cinco campos con dato en los dos materiales de prueba: los dos
interruptores y tres factores de la matriz, todo relleno automático del proceso de ECA que se borró en
el paso 1.

El orden fue al revés de lo que parece natural, y a propósito. Cuando se borra un campo, Drupal
recorre la configuración que dependía de él y le pide que se arregle sola; los displays saben hacerlo,
pero para no depender de eso se quitaron primero todas las dependencias a mano, se comprobó que no
quedaba ninguna, y solo entonces se borró. **El guion no borra si la comprobación no sale limpia.**

Y así aparecieron dos restos que el paso 4 no había visto, los dos invisibles:

- En el listado de materiales quedaban tres columnas de Óscar en el **orden de las columnas de la
  tabla**, que es una lista aparte de la lista de campos. No se dibujaban, porque el campo ya no
  estaba, pero ahí seguían.
- En el editor de líneas de pedido, una columna tenía escrito `@field_tec_price_by_package` en la
  casilla de reescribir el resultado, **con la casilla sin marcar**. Views guarda lo último que
  alguien escribió aunque desactive la reescritura, así que era texto muerto esperando a que alguien
  volviera a marcar la casilla. Vaciado.

**Un cambio que no era de borrar, pero que había que hacer:** en la ficha del material se veía el
pedido mínimo de Óscar, `field_tec_purchasing_moq`, que es un desplegable y estaba vacío en los dos
materiales, y estaba escondido el bueno, `field_tec_moq_quantity`, que es la cantidad y es el que usan
las vistas. Al llevarse el de Óscar, la ficha se habría quedado sin pedido mínimo, así que el bueno
ocupa su sitio.

##### El calculador de costes del formulario, y lo que revela

El JavaScript que calcula los dos costes derivados —`field_tec_price_uos` (Inventory Cost) y
`field_tec_price` (Consumption Cost)— **acertaba de casualidad**. Buscaba el factor de compra a
inventario probando seis selectores en fila: los tres primeros apuntaban a campos de Óscar, que al
estar escondidos del formulario no aparecen, y el cuarto era el campo bueno. Funcionaba porque los
tres primeros fallaban. Ahora apunta directo a los cinco campos que necesita, y de paso se le quitó lo
que sobraba: los dos campos de precio de Óscar que rellenaba, un temporizador que recalculaba dos
veces por segundo para siempre —con una limpieza que nunca se ejecutaba, porque escuchaba
`beforeunload` en el formulario y ese evento solo salta en la ventana— y una docena de mensajes de
consola. Comprobado que encuentra los cinco campos, porque si un identificador no coincide **se calla
y no calcula, sin dar error**.

Y aquí está el hallazgo que importa para el importador: **esos dos costes solo se calculan en el
navegador**. Se ve en los dos materiales de prueba. El que se creó a mano tiene los dos costes puestos,
80 / 0,40 = 200 y 200 / 10000 = 0,02. El que se creó por código, que es como va a entrar el
importador, **los tiene vacíos**. O sea que los 857 materiales entrarían sin coste de inventario y sin
coste de consumo, que son justo los dos números con los que se calcula lo que cuesta producir. La
decisión de no importarlos era correcta, pero da por hecho un cálculo automático que hoy no existe
fuera del formulario. **Hay que llevar ese cálculo al servidor**, con ECA o con un `presave`, antes de
importar. Es la pieza que falta y no es grande: son dos divisiones.

Un detalle a decidir con eso: los dos campos guardan **dos decimales**. Con el material de prueba el
coste por centímetro sale 0,02, que ya está en el límite. Un material más barato daría 0,008 y se
guardaría como 0,01, un 25% de error arrastrado a todos los BoM. Si el coste de consumo va a
usarse para costear producción, ese campo necesita cuatro decimales.

##### La prueba de humo, ocho pantallas más

Al ir a comprobar la ficha del material salió que **la prueba de humo no miraba ninguna página de
taxonomía**, y los materiales y las unidades son términos de taxonomía. O sea que el dato que más se
edita del ERP no estaba probado. Añadido: ahora coge la ficha más reciente de cada vocabulario. Pasó de
44 pantallas a 52, y las 52 cargan.

Siguen sin probarse dos, las dos del pedido de venta (`o/draft/%` y `o/pf/%/print`), porque no hay un
pedido de venta con líneas con el que pedirlas. Eran seis, así que se han recuperado cuatro.

##### La comprobación del ERP, recalibrada

Al pasarla al final daba **nueve fallos, y los nueve eran números suyos que este trabajo dejó
desfasados**: los cuatro procesos de ECA borrados, y siete recuentos de contenido que esperaban cero
porque se calibraron con la base recién vaciada, antes de que hubiera que fabricar un juego de pruebas
para poder comprobar las fórmulas. Actualizados los nueve, con su comentario y su fecha, como el resto
del fichero. Ahora da **47 de 47**.

Importa arreglarlo y no dejarlo: una comprobación que siempre sale en rojo se deja de mirar, y
entonces no avisa el día que el rojo es de verdad. **Y hay un cabo atado a esto**: las siete cifras de
contenido son el juego de pruebas, así que el día que se ejecute `borrar-el-juego-de-pruebas.php` hay
que devolverlas a cero. Está escrito en el propio fichero, al lado de las cifras.

##### ~~Un cabo cosmético, apuntado y no arreglado~~ Arreglado el 15 de agosto de 2026

En la vista de resumen de materiales, la pantalla maestra enseñaba identificadores en vez de etiquetas
en la columna de unidad de consumo: ponía `2, 706` donde debía poner `Centimeter (cm)`. Se confirmó
que **no se ve desde ninguna pantalla**, y se confirmó a conciencia, por los cinco caminos por los que
una pantalla puede colarse: Views nunca da ruta a la maestra, no hay ningún bloque de esa vista
colocado en ninguna región, el único objeto de configuración que la nombra es la pestaña «Material
Summary» de la ficha de pedido y dice expresamente `display: block_1`, ningún fichero de código la
incrusta a mano —7.195 mirados— y ningún contenido guardado la nombra. Al `2, 706` solo le veía la
cara quien abría la vista para editarla.

**El diagnóstico que estaba apuntado aquí era casi correcto y fallaba en lo que importaba.** Sí era
una agregación mal puesta, pero no la de `views_aggregator`, que es donde apetece mirar: era **la del
núcleo**, `group_type: sum` en esa columna, y la maestra es la única pantalla de la vista con
`group_by: true`. Cuando la agregación de una columna no es «agrupar», Views **cambia el manejador de
la columna por el numérico**, y el numérico no sabe nada de formateadores, así que el
`entity_reference_label` que la columna tenía configurado no se usaba nunca.

Y el número tampoco era lo que parecía: **`2, 706` no son dos números, es uno solo, el 2706**, que es
el identificador del término `Centimeter (cm)`, partido por el separador de valores múltiples de la
columna, que el manejador numérico reaprovecha como separador de miles. La segunda fila ponía
`2, 703`, o sea el 2703, `Sheet`. Ni el 2 ni el 706 existen como término, así que quien los busque por
separado no encuentra nada y pierde la tarde. Arreglado cambiando esa única clave a `group`. Las
pantallas hijas no cambian ni una celda, porque `block_1` tiene su propia lista de campos; lo que sí
hereda es el estilo, y por eso el estilo de `views_aggregator` es justo lo que no se podía tocar.

Quedan dos cosas dichas y no arregladas, las dos a propósito. **`Total required UoU (SUM)` pone
`75.0000` en vez de `75.00`** por el mismo mecanismo, pero ahí la suma es lo que se quiere y el número
es correcto, así que ponerla en `group` rompería la columna. Y `field_tec_quantity_input`, en la misma
maestra, tiene el mismo fallo pero está excluida y no la dibuja nadie: cambiarla movería la consulta
por una celda que no existe. Un barrido del ERP entero dice que no queda ningún otro caso.

### Los tres cabos que dejó el borrado

- ~~Retirar Marcas y Patrones de la configuración.~~ **Este punto estaba mal planteado, y la
  mitad de Marcas se arregló el 15 de agosto.** Ver la entrada del día en Hecho.

  Marcas no era una tarea de retirada: **la marca es obligatoria para crear un producto**, así que
  retirarla habría dejado el catálogo sin poder nacer. Lo que faltaba era la puerta, no la función.
  Ya está devuelta.

- **Decidir qué se hace con Patrones.** Aquí sí queda la decisión, y solo esta. El vocabulario
  `tec_patterns` sigue vacío con sus campos, dos vistas (`tec_patterns`,
  `tec_pattern_elements`), sus formularios y los permisos en tres roles. Su portada se borró hace
  tiempo, igual que la de Marcas, así que hoy no hay pantalla ni icono.

  **La diferencia con Marcas es que `field_tec_pattern` no es obligatorio**, o sea que el producto
  se crea sin él y retirarlo no bloquea nada. Pero tampoco corre prisa.

  **Dos cosas cambiaron el 15 de agosto, al retirar `tec_gui`, y hay que leerlas antes de decidir.**
  Patrones tenía un campo, `field_tec_pattern_boms`, que apuntaba a `tec_gui`, o sea al tipo de
  entidad muerto. Ese campo se fue con la retirada, porque ECK, al borrar un tipo de entidad,
  arrastra todos los campos de referencia que apuntan a él. No se perdió nada: estaba vacío, y no
  podía dejar de estarlo porque su destino no existía.

  Lo que sí hay que saber es la consecuencia. La vista `tec_pattern_elements` no tenía más campo
  que ese, así que **Drupal la dejó desactivada** al quedarse sin nada que mostrar. Y su bloque
  **sigue incrustado** en la ficha de un término de patrón, en el modo de vista `default` de
  `taxonomy_term.tec_patterns`. Hoy no molesta a nadie porque hay **cero términos de patrones**,
  pero el día que se cree uno y se pinte en ese modo, ese bloque apunta a una vista apagada. Si
  Patrones vuelve, esto se arregla antes: o se saca el bloque de la ficha, o se le devuelve a la
  vista un campo que mostrar. Si Patrones se retira, se va con el resto y da igual.

  **Si algún día se retira, no se puede borrar del tirón.** Los dos procesos de duplicar
  producto —`process_llpx4tp` y `process_icpsbgv`, "TEC Product: Duplicate product" y su
  clon— copian `field_tec_brand` y `field_tec_pattern` al duplicar. Si se borra el campo sin
  desmontar antes esos dos procesos, **se rompe el duplicado de productos**, que es una función
  viva y de las que más se usan. El orden es: primero quitar ese paso de los dos procesos, y
  después el campo, las vistas y el vocabulario. Es un cambio de configuración, o sea que viaja
  por Git y se puede revisar antes de aplicar.

- ~~Decidir qué se hace con 315 ficheros huérfanos.~~ **Hecho el 14 de agosto**, y con dos
  correcciones a lo que decía aquí. Ver la entrada del día en Hecho.

- ~~**Devolverle a la prueba de humo las seis pantallas que ha perdido.**~~ **Recuperadas las seis,
  y la prueba está hoy en 55 pantallas.** Con la base vacía no había con qué pedirlas, así que se
  saltaba `o/draft/%`, `po/draft/%`, `po/%/print`, `o/pf/%/print`, `po/%/x/pdf` y
  `tec_crm/%/reorder`, que eran las peores de perder: los dos editores de líneas —donde vivía el
  error 500 del 13 de agosto—, las dos de imprimir y la del PDF. Las seis responden 200.

  Se hizo en tres empujones. El 14 de agosto, lo baratísimo: **que lo diga**, porque antes se las
  saltaba en silencio y el resumen ponía "todas cargan", que es la clase de frase con la que uno
  despliega tranquilo sin motivo. Ese mismo día se le añadió lo que le faltaba por el otro lado, las
  doce portadas del ERP, que son nodos y no vistas y por eso no las descubría —por ese hueco se
  habían colado diez botones de crear escondidos—.

  Y el 15 de agosto se cerró, con un arreglo que no estaba previsto aquí: las dos pantallas de
  pedido de venta no salían porque el juego de pruebas fabricaba los pedidos **con estado `new`, que
  en este ERP no existe**, y las vistas filtran por borrador. O sea que no era la prueba de humo la
  que fallaba, era el dato. Corregido el guion que fabrica el juego y los pedidos que ya estaban con
  el estado inventado, las seis entran solas.

- ~~**El orden de las tallas no se guarda.**~~ **Arreglado el 15 de agosto de 2026**, el mismo día
  que se apuntó. Se arrastraban las tallas al orden que se quería en
  `/admin/structure/taxonomy/manage/tec_sizes/overview`, se pulsaba **Save**, y el orden no se
  guardaba. Importaba porque el orden de un catálogo de tallas no es alfabético ni casual —XS, S, M,
  L, XL, y las onzas de 6 a 20— y así salía mal en todos los desplegables y en los documentos de
  producción.

  **La guía de diagnóstico que estaba escrita aquí acertó de lleno**, y conviene decirlo porque es
  la primera vez que pasa: descartaba `admin_form_styles`, que efectivamente no era, y señalaba como
  sospechoso *otro `hook_form_alter` sobre `taxonomy_overview_terms`*. Era exactamente eso.

  El culpable es **`taxonomy_unique`**, que engancha su
  `hook_form_taxonomy_vocabulary_form_alter()` por el identificador de formulario **base**, y ese
  identificador lo comparten todos los formularios del vocabulario, incluida la pantalla de
  reordenar. Allí el botón de Guardar no lleva `#submit` propio, así que ponerle uno reemplaza la
  vuelta al manejador del propio formulario: el botón guardaba los ajustes del módulo y tiraba los
  pesos nuevos en silencio. El arreglo es una guarda de tres líneas, en
  `patches/taxonomy-unique-no-tocar-el-orden-de-terminos.patch`.

  **Costó encontrarlo porque por código sí funcionaba.** `submitForm()` guardaba los pesos sin
  queja, y solo fallaba pasando por HTTP, que es donde entra el gancho. Quien vuelva a tocar esto
  que no se fíe de una prueba programática. La que queda,
  `scripts/se-guarda-el-orden-de-las-tallas.php`, mira las dos caras a propósito: que el orden se
  guarde, y que `taxonomy_unique` siga rechazando nombres repetidos en su formulario de verdad, que
  es justo lo que se rompería si la guarda se pasara de ancha. Las quince tallas quedaron después en
  el orden del catálogo con `scripts/poner-las-tallas-en-su-orden.php`.

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

Sin prisa y sin orden fijo entre ellas.

- **Lista de compra sugerida por proveedor** — **hecha el 15 de agosto de 2026**. Vive en
  `/purchase`, con el icono trece de la portada, y la sirve
  `modules/custom/tec_production/src/Form/PurchaseListForm.php`. Un material entra en la lista
  cuando lo disponible deja de cubrir su punto de pedido, y disponible es el stock **más lo que
  ya viene de camino** en pedidos de compra abiertos, así que lo pedido esta mañana no se pide
  otra vez esta tarde. La cantidad sugerida tapa el hueco hasta punto de pedido más stock de
  seguridad, se redondea hacia arriba a unidades de compra enteras y nunca baja del MOQ; es una
  sugerencia, y todas las cantidades se pueden cambiar antes de crear el pedido. Cada proveedor
  lleva su total y su botón, que escribe el pedido de compra con sus líneas rellenas. No hay
  total general a propósito: cada proveedor factura en su moneda y sumarlos sería sumar bahts
  con dólares. Prueba de punta a punta en `scripts/funciona-la-lista-de-compra.php` —pide la
  pantalla, pulsa el botón, revisa el pedido y borra lo que ha creado— y la misma cuenta por
  línea de comandos en `scripts/que-hay-que-comprar.php`.

  Cinco cosas que ha dejado por el camino, por orden de importancia:

  1. **Ni un material tiene punto de pedido.** Sin punto de pedido no hay nivel por debajo del
     cual caer, así que hoy la pantalla no propone nada: solo enseña el apartado "N materials
     have no reorder point" con la lista de los que nadie vigila. Rellenar el punto de pedido de
     los 38 materiales del servidor es lo único que separa esta pantalla de ser útil, y es
     trabajo del dueño: cuánto aguantar de cada material no lo puede decidir un script.
  2. ~~**La numeración del flujo viejo repite números.**~~ **RESUELTO la noche del 16 de agosto de
     2026**, y de raíz: la numeración de compras y de ventas vive ahora en un solo sitio
     (`OrderNumber`, llamado desde un gancho de guardado), con el formato `CÓDIGO AA-NNN`, contador
     por contacto, reinicio anual y comprobación de que el nombre esté libre. Los tres sitios que
     fabricaban números —las dos ECA y la lista de la compra— han dejado de hacerlo. Ver la entrada
     «Un número por pedido, y uno solo» en la sección Hecho, incluida la mina que se descubrió por
     el camino: el parche del código corto vivía solo en el fichero que se ejecuta y no en el dibujo
     que edita la pantalla, así que estaba a un clic de desaparecer.
  3. ~~**Los dos caminos discrepan en el precio de la línea.**~~ **RESUELTO la noche del 16 de agosto
     de 2026**, dentro del trabajo de congelar el precio. `process_kryibry` copiaba en el precio de la
     línea el `field_tec_price` del material, que es el **coste de consumo**, mientras que el total lo
     calculaba `process_fpvka81` con el `field_tec_cost`, el **coste de compra**: en los pedidos del
     flujo viejo el precio que se leía y el total no cuadraban. Ahora la línea nace con el coste de
     compra y su importe se calcula con **su propio precio**, no con el coste que tenga el material
     hoy. Ver la entrada «Un pedido emitido vale lo que valía el día que se hizo» en la sección Hecho.
  4. **El pedido de compra no tiene estado de borrador.** `field_tec_po_status` admite un solo
     valor, `open`, y ese estado es justamente el que el tablero de stock cuenta como mercancía
     en camino. O sea que en cuanto se pulsa el botón, lo pedido cuenta como que viene, aunque
     nadie se lo haya mandado todavía al proveedor. Es coherente y evita pedir dos veces, pero
     si algún día se quiere un borrador de verdad hay que añadir el valor y decidir quién pasa
     de borrador a abierto. El sitio donde se decide qué cuenta como "en camino" es la constante
     `Purchasing::INCOMING_STATUS`.
  5. **El aviso de envío gratuito se queda fuera**, decisión del dueño del 15 de agosto. No
     existe ningún campo de umbral en el proveedor; el día que se quiera, es un campo nuevo en
     `tec_crm.tec_contact_organization` y un aviso por grupo en la pantalla.

  Y uno más que salió al revisar el modelo y **ya está arreglado**: el campo de líneas de la
  cabecera del pedido de compra, `field_tec_line_items`, declaraba que aceptaba líneas de
  **venta** (`tec_sales_order_line_item`), mientras que todos los pedidos de compra que existen
  llevan líneas de compra (`tec_po_line_item`). O sea que cada pedido de compra del ERP
  incumplía su propia definición desde el principio. No lo cantó nadie porque guardar por
  código no valida, y el flujo viejo guarda por código; se habría visto al editar un pedido por
  el formulario de administración, donde el buscador de líneas solo habría ofrecido líneas de
  venta. Corregido el 15 de agosto, y la prueba de la lista de compra pasa desde entonces
  `validate()` sobre el pedido que crea, que es lo que delata esta clase de fallo.

  Dos avisos para el despliegue: el permiso nuevo es `access tec purchase list` y lo tienen
  Manager y Executive, **no el supervisor de planta**, porque crear un pedido de compra
  compromete dinero; y la imagen del icono está en `sites/default/private`, que está fuera de
  git igual que los otros doce, así que hay que subirla al servidor a mano o el icono sale roto.
- **Ventas: alinear las tres pantallas y ponerles el IVA.** Apuntado el 17 de agosto de 2026, después
  de repasar cómo quedó ventas mientras se ordenaba compras. Son dos trabajos separables.

  El primero es barato y calca uno ya hecho. Las tres pantallas de venta cuentan lo mismo de tres
  maneras, que es la enfermedad que compras tenía la mañana del 16 de agosto:

  ```
  borrador   Item | Quantity | Picture | Product name | Product material | Color | Size | Price | Item total
  ficha      (sin rótulo) | Product name | Product material | Color | Size | Qty. | Sales price | Item total
  proforma   Item | Picture | Qty. | Product name | Color | Size | Price | Amount
  ```

  La cantidad es `Quantity` en una y `Qty.` en las otras dos, y va en la posición segunda, sexta y
  tercera; el precio, `Price`, `Sales price` y `Price`; el importe, `Item total`, `Item total` y
  `Amount`. La ficha no lleva ni contador ni foto, y la proforma no lleva el material. Sirven de
  plantilla `las-dos-pantallas-de-compra-se-parecen.php` y `el-impreso-se-lee-como-el-borrador.php`.

  El segundo es el IVA, y está entero. Las dos pantallas de compra llevan el pie `Subtotal / VAT /
  Total` con el manejador `tec_purchase_vat_totals`; las tres de venta siguen con el `Grand Total` del
  `attachment_1` compartido. Cuando se decidió el alcance el 16 de agosto se eligió «compras» a
  propósito, pero el dueño ya dijo cómo tiene que ser en ventas: **el porcentaje sale del país del
  cliente y se estampa al crear el pedido**, editable después, igual que en compras. `Vat::breakdown()`
  y el manejador de Views no distinguen de qué lado vienen, así que el trabajo es el campo en el pedido
  de venta, el gancho que lo estampa desde la ficha del cliente, y colgar el pie en las tres pantallas.

  Tres cosas que **no** hay que llevar, porque ya funcionan igual en los dos lados: la numeración
  (`OrderNumber` conoce las ventas y las compras, con el mismo formato y el mismo contador que no
  retrocede), el precio congelado (ventas nunca tuvo el fallo: su ECA siempre multiplicó por el precio
  de la línea) y el filtro de líneas sin cantidad, que la ficha y la proforma ya tienen y el borrador no
  —y así debe ser, porque el borrador es donde se teclean las cantidades—. Un detalle feo de paso: la
  ficha de ventas lo hace con **dos** filtros sobre el mismo campo, `!= 0` y `not empty`, donde compras
  usa uno solo `> 0`.

  Recibir no tiene equivalente y no lo tendrá por esta vía: el espejo de recibir mercancía es enviarla,
  y aquí el pedido de venta sale de producción, que ya tiene sus pantallas de cola y de parte de trabajo.
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

### El cron del sistema

Esta es la llamada que hay que dejar puesta en el servidor. Va en un fichero nuevo,
`/etc/cron.d/erp-cron`, propiedad de `root` y con permisos `644` (sin punto en el nombre, o `cron`
lo ignora sin decir nada):

```
*/15 * * * * www-data /usr/bin/php /var/www/erp/vendor/bin/drush.php --root=/var/www/erp --uri=https://erp.anvfightgear.com core:cron >> /var/log/erp/cron.log 2>&1
```

Antes, una sola vez, hay que crear la carpeta del registro, porque `www-data` no puede escribir en
`/var/log` directamente:

```bash
sudo mkdir -p /var/log/erp && sudo chown www-data:www-data /var/log/erp
```

**Cada quince minutos** porque el trabajo útil más frecuente que queda encendido, `field_cron`, se
programa cada tres horas: los dos que piden diez minutos son `dblog_cron`, que recorta el registro,
y `update_cron`, que consulta si hay actualizaciones, y ninguno de los dos nota cinco minutos de
retraso. Correr cada minuto —lo que recomienda Ultimate Cron cuando la máquina va sobrada— serían
1.440 arranques de PHP al día compitiendo por el único núcleo del droplet con los empleados que
están usando el ERP. Y no se pierde nada por esperar: Ultimate Cron tiene `catch_up` en 86.400
segundos, así que un trabajo que se salte su hora lo recoge la vuelta siguiente, no el día
siguiente.

**El usuario tiene que ser `www-data`, y esto es la trampa.** El instinto es poner `david`, que es
el dueño del código, y con `david` el cron parece funcionar: `drush status` contesta. Pero
`settings.php` es `root:www-data` con permisos `640` y `david` no está en el grupo `www-data`, así
que no puede leer las credenciales de la base de datos. Lo que saldría en `cron.log` cada cuarto de
hora es *"Drush was unable to query the database"*, y el cron llevaría meses sin correr con el
registro lleno de una queja que nadie mira. Es la misma pared del 14 de agosto que obligó a hacer
por el navegador lo que se quería hacer por SSH. `www-data` sí está en su propio grupo, sí lee
`settings.php`, y además es quien ya escribe en `sites/default/files`, así que los ficheros que
genere el cron —los estilos de imagen, sobre todo— nacen con el dueño correcto y no con uno que
Apache luego no pueda sobrescribir.

**Los otros tres detalles, y por qué están escritos así:**

- `/usr/bin/php … drush.php` y no `vendor/bin/drush`. El de `vendor/bin` es un script de shell que
  busca `php` en el `PATH`, y `cron` arranca con un `PATH` recortado a `/usr/bin:/bin`. Funciona en
  Ubuntu, pero depende de una suposición que no hace falta hacer: llamar al binario por su ruta
  absoluta no depende de nada.
- `--root=/var/www/erp`. `cron` no arranca en ninguna carpeta en particular, así que sin esto Drush
  no encuentra el sitio. Es un `drupal/legacy-project`: la raíz del sitio **es** la raíz del
  repositorio, no hay subcarpeta `web/`.
- `--uri=https://erp.anvfightgear.com`. Sin esto Drush usa `http://default`, y todo lo que el cron
  genere con una dirección absoluta dentro —los enlaces del informe de actualizaciones, los correos
  cuando el `smtp` esté puesto— sale apuntando a un dominio que no existe. Se ve en local: el
  informe de actualizaciones enlaza a `http://default/admin/reports/updates`.

El `>> … 2>&1` no es decoración: sin él, `cron` intenta enviar por correo cualquier cosa que el
comando escriba, y el correo saliente todavía no está configurado. Conviene añadirle rotación,
porque el fichero solo crece:

```bash
sudo tee /etc/logrotate.d/erp-cron > /dev/null <<'EOF'
/var/log/erp/cron.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

Comprobar que ha quedado bien, el día del despliegue: `sudo systemctl status cron`, esperar un
cuarto de hora y mirar que `/var/log/erp/cron.log` existe y está vacío (vacío es la señal buena: si
hay texto, es un error). La prueba de verdad es que en
*Informes → Informe del estado* desaparezcan los dos avisos de cron, el de "última ejecución" y el
de "trabajos atrasados" de Ultimate Cron.

### Lo que el ensayo no pudo probar

Se hizo sobre Windows y por HTTP, así que esto sigue sin verificar: HTTPS, la programación
del cron, el envío de correo, los permisos de las carpetas de archivos y el ajuste de
`opcache`.

## 8. Subir a Drupal 11 — HECHO el 13 de agosto de 2026

**El ERP corre en Drupal 11.4.5 con cero avisos de seguridad.** Empezó el día en la 10.2.4 con
ochenta y dos. El relato completo, paso por paso, está en el apartado de Hecho.

Lo que queda abierto de este apartado son dos cosas menores, al final: `minimum-stability` y las
dos carpetas de módulos apagados. Lo demás se deja escrito tal como estaba porque el plan se
cumplió casi entero y sirve para entender por qué se hizo en ese orden; donde la realidad se
desvió, está anotado en cursiva.

---

Auditado el 13 de agosto por cuatro revisiones en paralelo. El sitio corría **Drupal 10.2.4, que
ya no tenía soporte**.

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

1. ~~**Meter en Composer los módulos sueltos y reconciliar las versiones.**~~ **Hecho el 13 de
   agosto.** `composer.json` describe la realidad, `composer validate` dice *valid* y el
   inventario da **0 descuadres** entre el disco y el `lock`. Fueron 28 módulos, no 24, más los
   dos que Composer ni sabía que existían (`quicktabs` y `views_entity_form_field`) y tres
   parches hechos a mano que nadie había registrado. El detalle está en el apartado de Hecho.

   Vale la pena guardar **por qué el problema era invisible**, porque volverá a pasar: Composer
   no mira los ficheros de los módulos, se fía de su propia contabilidad en
   `vendor/composer/installed.json`. Ahí seguía apuntado `ds` 3.19.0 aunque en disco hubiera un
   3.22, y por eso `composer install --dry-run` decía tan tranquilo "nada que hacer". La única
   forma de detectarlo es comparar a mano, que es lo que hacen ahora
   `scripts/inventario-composer.php` (disco contra `lock`) y `scripts/comparar-con-original.php`
   (disco contra el paquete oficial de drupal.org).
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
   Twig, Guzzle y los polyfills están todos en la lista de lo que sube.

   *Hecho el 13 de agosto. Se cumplió lo previsto —quedaron 2 avisos— salvo una cosa: aquí se
   decía que `views_aggregator` dejaría de dar problemas solo, y no fue así. Su error 500 no
   venía del núcleo sino de un fallo propio, y hubo que parchearlo. Ver la Fase 2 en el
   apartado de Hecho.*
4. **ECA, de la rama 1 a la 2**, con sus submódulos y con `bpmn_io` de la 1 a la 2. Es un salto
   de versión mayor y mueve las transacciones de inventario, así que es la parte que más hay
   que probar. Importante: **no ir a ECA 3**, que exige Drupal 11.2 o más.

   *No corre prisa por seguridad, comprobado el 13 de agosto.* El único aviso que queda de los
   82, `SA-CONTRIB-2026-074`, dice que se arregla en ECA 2.1.20 y que la rama 1 no tiene
   solución. Pero solo es explotable si el sitio usa la acción "Render: Twig" del submódulo
   `eca_render`, y ese submódulo **está desinstalado** y no lo menciona ninguno de los 36
   procesos. Así que este salto se hace cuando toque, por mantenimiento, no con prisa.
5. **Los temas.** `dxpr_theme` va de la versión 5 a la 8, y su rama actual lleva congelada desde
   enero de 2024. Su tema base, `bootstrap5`, va de la 3 a la 4. Y Gin, el de administración, de
   la 3 a la 4.

   *Rebajado el 13 de agosto: ya no es "la parte cara".* Aquí se decía que `dxpr_theme` cerraba
   el paso porque depende de `color`, que salió del núcleo, y porque sobrescribe Modernizr y
   Classy. Lo primero está resuelto desde la Fase 1: el `color` de contrib está anclado en
   `composer.json` y sale limpio en el informe. Lo segundo no lo detecta el analizador, así que
   habrá que mirarlo a ojo al probar. Y la versión que corremos declara `>=9.3`, que **incluye la
   11**. Actualizarlo sigue siendo buena idea por lo congelado que está, pero no bloquea.
6. **El resto de módulos**, empezando por `field_permissions`, cuya versión instalada
   **prohíbe expresamente** Drupal 11, y por los que cambian de versión mayor: `select2`,
   `field_group`, Better Exposed Filters, `flag`, `mimemail`, `module_filter`, `mail_login`,
   `quicklink`, `coffee`, `flood_control`, `views_bulk_edit` y `xls_serialization`.

   *Acotado el 13 de agosto por el informe de `upgrade_status`.* De los 115 proyectos, **60 solo
   necesitan que su nueva versión declare la 11** —no hay código que tocar— y **45 ya están
   limpios**. Los únicos cuatro con código que Drupal 11 elimina son `flag` (tres llamadas a
   `user_roles()`), `views_entity_form_field` (tres a `getEntityTranslation()`), `flood_control`
   (una a `user_role_names()`) y ECA (una constante de clase, y esa aún funciona en la 11). Los
   cuatro se resuelven actualizando, no programando. Lo que sí queda por comprobar módulo a
   módulo es si existe versión publicada compatible con la 11, cosa que el informe no mira.
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

**Pendiente de decidir.** Sigue puesto en `composer.json`, y ya no hay ningún paquete anclado a
un commit: los tres que quedaban —`eck`, `conditional_fields` y `ultimate_cron`— pasaron a
versión publicada el 13 de agosto.

Lo que impide ponerlo en `stable` sin pensar es que hay paquetes pedidos expresamente en fase
beta o alfa: `feeds` en `^3.0@beta`, `phone_number` en `^2.0@alpha`, `feeds_tamper` en la
`2.0.0-rc1`, `scan_code` en `^1.0@beta` y `pwa` en la `2.1.0-beta7`. Esos llevan la marca en su
propia restricción, así que en teoría seguirían resolviendo con `stable`, pero conviene probarlo
con `--dry-run` antes de tocarlo, no darlo por hecho.

Y conviene recordar por qué importa, ahora que lo hemos pagado: el 13 de agosto, al ponerle a
ECA su número de versión, apareció un fallo **crítico** de seguridad que llevaba dieciséis meses
sin salir en ningún informe. No porque nadie mirara, sino porque un módulo sin versión no se
puede cruzar con la lista de avisos. Una rama de desarrollo no es solo código sin bendecir: es
código fuera del radar.

### Dos carpetas de más

En `modules/custom` hay **seis** carpetas y solo cuatro módulos activos. `tec_brands` y
`tec_crm` están en disco, sin código PHP, solo con configuración, y desinstalados desde hace
tiempo. Se pueden borrar del repositorio.

## 9. Deuda técnica

Nada de esto corre prisa, pero conviene que esté escrito para que no se descubra por sorpresa.

- **Un token de campo en un patrón de direcciones se rompe si alguien enciende Layout Builder.** Los
  tokens del tipo `[entidad:campo]` o `[entidad:campo:0]` **no leen el campo, lo renderizan**, y para
  renderizarlo usan la vista `default` de la ficha. Con Layout Builder encendido en esa vista, los
  campos listados no son lo que se pinta —se pintan las secciones—, así que el token sale vacío.

  Ya cobró una vez, la noche del 16 de agosto de 2026: el patrón de las fichas de CRM era
  `/[tec_crm:field_tec_contact_type:0]/[tec_crm:id]` y la ficha 33 pasó de `/customer/33` a `/33`. Se
  arregló leyendo el término directamente con `:0:entity:name`.

  **Sigue vivo en el patrón de los materiales**, `/material/[term:field_tec_material_type]/[term:name]`.
  Hoy funciona porque los términos de taxonomía no se maquetan con Layout Builder. El día que alguien lo
  encienda para los materiales, todas las direcciones de material se convertirán en `/material//nombre`,
  y nadie se dará cuenta hasta que empiecen a aparecer redirecciones raras: la dirección sigue siendo
  única, el sitio sigue funcionando, y el fallo solo asoma cuando se guarda una ficha. Cambiarlo a
  `[term:field_tec_material_type:0:entity:name]` cuesta un minuto y quita la trampa; no se ha hecho ya
  porque cambiar el patrón regenera las direcciones de los 38 materiales y eso deja 38 redirecciones que
  habría que revisar.

- **Las pestañas de Drupal no se leen en este sitio, y solo las ve el administrador.** Dos cosas
  independientes que se suman. La primera es de DXPR: en `css/components/tabs.css` saca la barra de
  pestañas del flujo con `position: absolute` y la sube su altura entera con `translate(-50%,-100%)`,
  así que aterriza encima de la cabecera pegajosa y queda ilegible. La segunda es del bloque
  `dxpr_theme_local_tasks`, que tiene una condición de visibilidad por rol y solo deja pasar a
  `administrator`; para los demás roles no hay barra de pestañas en absoluto, ni View ni Edit ni
  Delete.

  Consecuencia práctica: **cualquier función que se cuelgue de una pestaña es invisible**. Ya pasó una
  vez, con `Receive`, y costó seis horas de trabajo que nadie podía usar. Mientras esto siga así, todo
  lo que tenga que encontrar una persona va en un botón dentro de la pantalla, no en una pestaña.

  Arreglarlo es CSS de un tema de contribución, así que la sobrescritura tendría que vivir en un módulo
  propio para que no se la lleve la próxima actualización. Lo del rol es un clic en la administración
  de bloques, pero antes hay que decidir si se quiere que los demás roles vean las pestañas de editar y
  borrar.

- ~~**`tec_gui`: un tipo de entidad abandonado que deja el informe de estado en rojo.**~~ **Retirado
  el 15 de agosto de 2026**, entero: el tipo de entidad, sus trece tablas, los dos campos que le
  apuntaban, las dos banderas, el selector, el modo de vista y los veintinueve permisos. Ver la
  entrada del día en Hecho. El informe de estado se queda con **un solo error, el HTTPS de local**.
  Lo que queda debajo es cómo se veía el problema antes de resolverlo, que explica por qué se hizo
  ahora y no después.

  Encontrado el 13 de agosto al subir a Drupal 11, al revisar los requisitos del sitio. Es un tipo de
  entidad de ECK, "GUI", con **cero** contenidos, pero con sus doce tablas creadas en la base y
  un campo suyo, `field_tec_gui_product`, colgado de las líneas de pedido de venta. Ese campo
  tiene **20 filas que apuntan a productos GUI que no existen**.

  Drupal avisa con un error de que la definición está descuadrada: el registro interno de "qué
  hay instalado" no coincide con lo que dice la configuración. No rompe nada —el ERP pasa las 35
  comprobaciones y las 33 páginas cargan— pero mantiene dos errores en rojo permanentes en el
  informe de estado, y eso hace que un error de verdad pase desapercibido el día que aparezca.

  **No lo causó la subida a la 11**, ya venía de antes; encaja con la experimentación del
  programador anterior. Arreglarlo es decidir entre dos caminos: borrar el invento entero (el
  tipo de entidad, el campo, las tablas y las 20 referencias muertas), que es lo limpio, o
  simplemente anotar la definición en el registro para que deje de avisar, que es lo barato.
  Antes de elegir hay que mirar qué hacen esas 20 líneas y si alguien las usa.
  Diagnóstico: `drush scr scripts/que-esta-descuadrado.php`.

  **Lo que se averiguó el 14 de agosto, y que hace la primera opción mucho menos barata de lo que
  parecía**: `field_tec_gui_product` no es un campo cualquiera colgado de las líneas de venta, es un
  campo **obligatorio** de ese subtipo. Borrar `tec_gui` significa por tanto tocar la definición de
  las líneas de pedido, que es la pieza más usada del ERP. Y las cincuenta fichas que parecían
  perdidas no lo estaban del todo: vivían en trece tablas temporales de una actualización a medias,
  ya tiradas ese mismo día después de comprobar que las tablas de verdad estaban vacías y que Drupal
  no veía ninguna. O sea que ahora `tec_gui` está limpio de datos, pero **estructuralmente sigue
  enganchado**, y ahí es donde está el trabajo.

- **Un tipo de importación huérfano: `tec_products_csv_importer`.** Encontrado el 13 de agosto
  al revisar Feeds. Hay **dos importaciones creadas** que lo usan, "Primo products" con 28
  elementos y "Trust products" con 92, pero **el tipo ya no existe en la configuración**: solo
  quedan definidos `tec_bom_items_importer` y `tec_inventory_csv_importer`. Dos importaciones
  apuntando al vacío.

  Esto es directamente relevante para la tarea de **arreglar el importador** del apartado 3: hay
  que averiguar si el importador de productos se borró a propósito, si se renombró, o si se
  perdió en alguna importación de configuración. Y decidir si esas dos importaciones se
  recuperan o se borran.
  Diagnóstico: `drush scr scripts/quien-usa-feeds.php`.

  **Pista fuerte encontrada el 15 de agosto, al retirar `tec_gui`**: entre sus trece tablas había
  una `tec_gui__feeds_item`, que es la que Feeds crea para llevar la cuenta de lo que ha importado.
  O sea que hubo un importador configurado para fabricar fichas GUI, y GUI era el producto de
  entonces. Eso apunta a que esas dos importaciones huérfanas, "Primo products" y "Trust products",
  no importaban a `tec_product` sino a `tec_gui`, y que el tipo de importación desapareció con el
  cambio de un producto al otro. Si es así, **no hay nada que recuperar**: recuperarlas sería
  recuperar un importador que llena una entidad que ya no existe. Se decide con la tarea del
  importador, no antes.

- ~~**`tec_app_link`: otro tipo de entidad abandonado, y este ya sin tablas.**~~ **Retirado el 15 de
  agosto de 2026.** Salió a la luz de rebote, al guardar los tres roles para quitarles los permisos
  de `tec_gui`: Drupal avisó de que retiraba **permisos que no existen** —`create`, `edit any`,
  `view any` y en dos roles también `delete any tec_app_link entities`—. Los retiró él, no nadie; un
  rol no puede guardar permisos de algo que el sitio no conoce.

  Era un tipo de entidad de ECK con seis subtipos, uno por cada enlace de redes sociales de la
  portada: Facebook, Instagram, LINE, WhatsApp, X y teléfono. **Nunca tuvo una sola ficha dentro.**
  El grueso se había ido ya el 14 de agosto con `scripts/borrar-lo-que-no-usa-nadie.php`, que se
  llevó los seis subtipos, el tipo y sus dos tablas. Lo que se retiró el 15 fue la cola.

  **Lo apuntado aquí estaba a medias, en las dos direcciones**, y conviene saber en qué: lo de "cero
  tablas" era correcto; lo de los permisos sueltos **ya no era verdad** cuando se fue a mirar, porque
  Drupal los había quitado solo al guardar los roles en la limpieza de `tec_gui`, o sea que este
  apunte se escribió antes de ese guardado. Y lo que sí quedaba no estaba apuntado: **ocho** entradas
  en `cancel_button.settings` y no una —las seis parejas tipo-subtipo, el tipo suelto y el tipo de
  subtipo—, más una definición de entidad instalada huérfana, `tec_app_link_type`, que nadie había
  visto. Ningún campo de otra entidad le apuntaba, así que no hubo nada parecido al campo obligatorio
  que tenía `tec_gui`. Queda `scripts/quitar-a-app-link-del-erp.php` y dos guiones de reconocimiento.

- ~~**Quedan tres definiciones de entidad instaladas huérfanas, y una es el gemelo de `tec_gui`.**~~
  **Retiradas el 15 de agosto de 2026.** Hallazgo del mismo día, al retirar la cola de
  `tec_app_link`. ECK deriva por cada tipo de entidad un tipo de configuración aparte para sus
  subtipos, y al borrar un tipo se retira su definición pero **no la del subtipo derivado**. O sea que
  la limpieza de `tec_gui` dejó exactamente el mismo resto que hubo que quitar en `tec_app_link`.

  Lo que hacía esto peligroso no era el resto, que es inofensivo, sino que **no se veía**: no pinta
  nada en rojo en el informe de estado, porque `getChangeList()` del núcleo solo recorre los tipos que
  existen hoy y a un tipo que ya no existe nunca lo compara con nada. Y el guardián *"no queda nada de
  tec_gui"* daba el visto bueno igual, porque mira tablas, campos y permisos y no las definiciones
  instaladas.

  **Y aquí el diagnóstico que se escribió primero estaba equivocado, esto conviene leerlo.** Se dio
  por hecho que las otras dos, `commerce_product_variation_type` y `commerce_store_type`, eran de un
  Drupal Commerce desinstalado. **No lo eran.** Al abrir el objeto serializado, las tres dicen
  proveedor `eck` y clase `Drupal\eck\Entity\EckEntityBundle`; si fueran de Commerce, el proveedor
  sería `commerce_product` o `commerce_store`. Y sus etiquetas las delatan: **«temp1 type»** y **«assd
  type»**. O sea que alguien creó con ECK dos tipos de entidad reutilizando dos nombres de máquina de
  Commerce, los llamó «temp1» y «assd», y los borró. Misma especie que los otros dos, mismo origen,
  ninguna razón para tratarlas distinto.

  El comprobador ya no está ciego a esto: tiene un guardián nuevo, **`ninguna definicion de entidad
  instalada huerfana`**, que compara las claves del almacén con los tipos que el sitio conoce hoy, sin
  lista de nombres a mano, y mira las dos entradas que guarda cada tipo, porque una retirada hecha a
  mano por la tabla puede llevarse una y dejar la otra. Se probó **plantándole un fantasma falso
  delante**: con `tec_tipo_de_mentira` metido lo cantó por su nombre, mientras los dos guardianes
  vecinos seguían en verde. Queda `scripts/probar-el-guardian-de-las-definiciones-huerfanas.php` para
  repetir esa prueba.

- **Quedan 55 tablas de Commerce con 180 filas dentro, y son el catálogo de producto de la generación
  anterior.** Hallazgo del 15 de agosto, de rebote al investigar los fantasmas. Commerce **sí estuvo
  instalado** aquí, y esto es lo que dejó: `commerce_product_variation__field_tec_bom` con 46 filas,
  `commerce_product_variation` con 14, `commerce_product` con 8, `commerce_store` con 1, y campos
  `tec_` dentro —`attribute_tec_color`, `attribute_tec_size`, `field_tec_pattern_shin_guard`—, o sea
  la misma época que `tec_gui`.

  De código no queda **nada**: ni módulo instalado ni en el disco, no está en `core.extension`, no
  guarda versión de esquema, no aparece en `composer.json` ni en `composer.lock`, y no hay
  configuración, campos, vistas, rutas ni permisos. Son tablas inertes que nadie gobierna. Con ellas
  quedan sueltas 3 entradas de `entity.definitions.bundle_field_map` y 2 de estado
  (`commerce.inbox_messages_cron_last` y `uc-progress: commerce_cron`).

  **No se han tocado, y es una decisión aparte**, más grande que la de los fantasmas: son datos
  históricos, aunque de un producto que ya no existe. Lo que sí conviene saber es por qué no se puso
  un guardián de tablas huérfanas al lado del de definiciones: saldría en rojo desde el primer día por
  estas 55, y un rojo permanente es lo que enseña a no mirar los rojos.

- ~~**Trece tablas `tmp_b44c2b*` en la base de datos.**~~ Tiradas el 14 de agosto de 2026, y no eran
  restos de Backup and Migrate como se suponía aquí: eran las tablas de `tec_gui` con cincuenta
  fichas dentro, aparcadas por una actualización de tipo de entidad que se quedó a medias. Se
  comprobó antes que las tablas de verdad existen y están vacías y que Drupal no ve ninguna ficha.

- **Borrar un material en uso avería en silencio las líneas que lo usan.** Descubierto el 13 de
  agosto durante la prueba funcional. **Hecha la mitad el 15 de agosto de 2026: ya no se puede
  borrar. Falta la otra mitad, que el proceso aguante.**

  Cuando una línea de pedido tiene en su BoM un material que ya no existe, el proceso de ECA
  que calcula el total arranca, se para en seco al intentar cargar ese material y **deja puesta la
  bandera de bloqueo**. No da error ni aviso: el total simplemente deja de actualizarse, y como la
  bandera se queda puesta, esa línea no vuelve a recalcular nunca más aunque se arregle el material.

  Hacían falta dos cosas y solo está una. **La puesta**: el formulario de borrar un material lo
  impide si algo le apunta, y dice qué se lo impide, con nombre y enlace, hasta diez y luego cuántos
  más quedan. Se eligió impedir y no avisar porque un aviso que se lee después de pulsar Borrar no
  sirve de nada cuando lo que rompe es invisible y no se arregla volviendo atrás. **La que falta**:
  que el proceso, si no encuentra el material, se salte ese elemento y siga en vez de morirse. Sin
  eso, cualquier vía que no pase por el formulario —una importación, un guion, un borrado en
  cascada— vuelve a dejar líneas con la bandera puesta.

  Mientras tanto, la señal para detectarlo es que queden banderas `%lock%` puestas:
  `SELECT flag_id, COUNT(*) FROM flagging WHERE flag_id LIKE '%lock%' GROUP BY flag_id`. En reposo
  no debe salir ninguna, y el comprobador ya lo exige: la excepción de `tec_eca_gui_lock` que se
  perdonaba aquí resultó ser una marca huérfana sobre una ficha que no existía, y se fue con
  `tec_gui`.
- ~~**Una errata mete "emergencias" falsas en el registro.**~~ Arreglado el 14 de agosto de 2026,
  y no era un carácter sino dos: ver abajo, en Hecho. El aviso que aquí quedaba escrito —"se
  arregla cambiando un carácter en `config/sync/eca.eca.process_rxuimsq.yml`, línea 166"— habría
  durado hasta que alguien abriera el modelo en el editor de ECA, porque la severidad está
  **duplicada** en el XML del BPMN.
- **La tabla de líneas de pedido no tiene modo de vista configurado.** El formulario del pedido
  de venta usa el widget de `ief_table_view_mode`, pero con el ajuste `view_mode` vacío, así que
  dibuja las columnas genéricas de Inline Entity Form: Título, Tipo y Operaciones. Es decir, el
  módulo está instalado y no está haciendo lo único para lo que sirve, que es dejarte elegir qué
  columnas ves. Viene de antes de la actualización del 13 de agosto —la configuración en Git es
  idéntica—, así que no es una regresión, pero es una mejora fácil y visible: definir un modo de
  vista con las columnas que de verdad importan de una línea de pedido.
- **`composer update` a pelo sigue sin poder ejecutarse; dirigido, sí y mucho.** Matizado el 13
  de agosto después de subir el núcleo y reconciliar 28 módulos, todo con actualizaciones
  dirigidas y sin un solo conflicto. Lo que no se ha probado es un `update` sin argumentos, y no
  hay motivo para probarlo: no aporta nada que no dé la vía dirigida y sí puede desmontar el
  sitio entero de una vez.
- **Ojo con `composer require --dry-run`: no es de solo lectura.** Escribe en `composer.json`
  antes de simular. Si se usa, hay que deshacer el cambio a mano, no con `git checkout` del
  fichero, porque eso también borra el trabajo sin confirmar que haya en él. Costó hora y media
  el 13 de agosto.
- **El `PATH` de Windows se trunca a 8.191 caracteres, y en una sesión larga eso pasa.** El 13 de
  agosto la ruta de Git había acabado repetida 72 veces, el `PATH` llegó a 8.823 caracteres y
  Windows cortó por lo sano. El síntoma fue que dos guiones que habían funcionado media hora
  antes empezaron a devolver resultados absurdos, sin decir en ningún momento que no encontraban
  Git. Los guiones ya localizan el ejecutable por su ruta completa, pero el aviso vale para
  cualquier orden: si algo deja de funcionar sin motivo, mirar `$env:PATH.Length` antes que nada.
- **Quedan tres módulos anclados a un commit en vez de a una versión publicada**: `eck`,
  `conditional_fields` y `ultimate_cron`. Los tres están a medio camino entre dos versiones, así
  que anclar el commit era lo único que reproducía exactamente lo que corre. Funciona y es
  reproducible, pero **no reciben avisos de seguridad**, que es justo el agujero por el que se
  nos coló el fallo crítico de ECA durante dieciséis meses. Hay que sacarlos de ahí:
  - **`eck` a la 2.1.0**, que es estable. Estudiado a fondo el 13 de agosto; el resumen está
    debajo. **Es obligatorio para Drupal 11**: lo que corremos declara `^9.4 || ^10` y la 2.1.0
    declara `^11`. Merece su propia ronda porque todas las entidades del ERP son de ECK, pero el
    cambio es más pequeño de lo que aparenta.
  - **`conditional_fields` a la 4.0.0-alpha6**, que está a un paso del commit anclado.
  - **`ultimate_cron` a la 8.x-2.0-beta1** (noviembre de 2024, cinco meses por delante de lo que
    corremos). Va junto con la tarea de programar el cron, que sigue pendiente.

  **El salto de `eck`, estudiado.** De lo que corremos (un commit de febrero de 2024) a la 2.1.0
  (marzo de 2026) hay 22 commits. A primera vista asusta —32.777 líneas borradas— pero casi
  todas son de "dejar de soportar Drupal 7": el código de migración y un fichero de pruebas de
  29.251 líneas. **El cambio real son 34 ficheros, 640 líneas nuevas y 436 quitadas.**

  - **Una sola actualización de base de datos**, `eck_update_8007`, que añade una clave
    `standalone_url` a cada tipo de entidad con valor verdadero, es decir manteniendo lo que hay:
    los pedidos seguirán teniendo su dirección propia del tipo `/tec_order/348`.
  - **La interfaz pasa a exigir que las entidades sean publicables y fechables.** Suena a
    problema porque `tec_line_item` no tiene campo de estado ni de autor y `tec_app_link` no
    tiene fechas, pero está contemplado: todos los métodos comprueban antes si el campo existe, y
    `isPublished()` devuelve verdadero cuando no lo hay, que es lo correcto para una línea de
    pedido.
  - **Las claves de entidad pasan a asignarse según los campos que tenga cada tipo.** Hasta ahora
    a todos se les ponía `label` y `published` aunque no tuvieran título ni estado. Es el arreglo
    de un fallo, pero cambia la definición de la entidad, así que **habrá que pasar las
    actualizaciones de definición** después de instalar y mirar con lupa `tec_line_item`, que es
    el tipo al que le desaparece la clave `published`.
  - **Cambia cómo se dibujan las entidades**, de un gancho a una clase dedicada. No nos afecta:
    no tenemos plantillas propias de ECK ni nadie engancha ese gancho.
  - **Nuestro código no menciona ni una clase de ECK**, comprobado en todos los módulos
    personalizados. El riesgo de integración es cero por nuestra parte.
  - De propina vienen arreglos que aquí no hemos sufrido pero podríamos: nombres de paquete que
    empiezan por número, tipos llamados `block` o `custom`, y la limpieza de cachés de plugins
    cuando se crea o modifica un tipo de entidad.

  Plan cuando toque: copia de la base de datos, subir el módulo, `eck_update_8007`, las
  actualizaciones de definición de entidad, y después las 35 comprobaciones y las quince páginas.
  Vigilar en concreto la pantalla de líneas de pedido y la de inventario.
- **Quince carpetas de módulos apagados siguen ocupando sitio en `modules/contrib`**, ninguna
  gestionada por Composer: `base_field_override_ui`, `bootstrap4_modal`, `currency`,
  `entity_browser_enhanced`, `entity_reference_modal`, `field_validation`, `integer_to_decimal`,
  `key`, `pace`, `plugin` y `views_auto_refresh`, entre otras. No se ejecutan, pero engordan el
  repositorio y aparecen en las búsquedas. Una de ellas, `integer_to_decimal`, lleva cambios
  hechos a mano, así que si algún día se reactivara habría que capturarlos antes. Borrarlas es
  seguro; solo hay que hacerlo con calma y comprobando una por una.
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

  **Cuidado con la conversión, que tiene trampa.** El 13 de agosto se convirtieron tres parches
  con esto, y no sirvió de nada:

  ```powershell
  $t = [System.IO.File]::ReadAllText($f)                       # MAL
  [System.IO.File]::WriteAllText($f, $t, $utf8SinBom)
  ```

  El fichero sale sin marca de orden de bytes, así que `ReadAllText` supone UTF-8, lee los ceros
  intercalados como caracteres nulos válidos y los vuelve a escribir igual. El fichero queda
  byte por byte idéntico y la conversión parece haber funcionado. Hay que decirle de qué
  codificación viene:

  ```powershell
  $b = [System.IO.File]::ReadAllBytes($f)
  if ($b.Length -gt 1 -and $b[1] -eq 0) {                      # segundo byte cero: es UTF-16
    $t = [System.Text.Encoding]::Unicode.GetString($b) -replace "`r`n", "`n"
    [System.IO.File]::WriteAllText($f, $t, (New-Object System.Text.UTF8Encoding $false))
  }
  ```

  La forma de comprobar que de verdad se convirtió es mirar el tamaño: tiene que bajar
  aproximadamente a la mitad.
- **Referencias muertas a la IA en nuestros propios módulos.** El código de los módulos de IA ya
  no está en el disco —`ai_interpolator` y `ai_interpolator_openai` se los llevó la subida del
  núcleo, y `ai` y `ai_interpolator_eca` se borraron a mano el 13 de agosto, 908 ficheros—, y
  `composer.json` no los menciona. Lo que queda son referencias en texto: unos quince ficheros de
  `config/install` dentro de `tec_inventory`, `tec_brands` y `tec_crm`, más el `dependencies:` de
  los `.info.yml` de `tec_brands` y `tec_crm`. Solo se leen al instalar un módulo, y esos dos
  están desinstalados y además marcados para borrar (ver "Dos carpetas de más" en el apartado 8),
  así que hoy no hacen nada. La configuración activa tiene **cero** referencias, comprobado.
- **Quedan cuatro ficheros en `tec/backups`** (0,4 MB): una copia de la vista `tec_products` del 11
  de agosto y tres registros de la limpieza de pedidos de venta. Está excluida del repositorio y
  Apache le devuelve 403 a los `.yml`, así que no molesta a nadie; se deja como está.
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
- ~~**Quince avisos de PHP por cada carga de la pantalla de líneas de pedido.**~~ Arreglado el 14 de
  agosto de 2026 con el parche de una línea que se anunciaba, y probado con un pedido fabricado a
  propósito: la pantalla responde 200 y el registro se queda a cero. Detalle en Hecho, incluidas las
  dos maneras de escribir un parche que no se aplica.
- **`git status` enseña cincuenta ficheros modificados que no lo están.** Visto el 16 de agosto de
  2026 al empezar la recepción. El `git` de Laragon trae `core.autocrlf = true` en su configuración
  del sistema —`C:/laragon/bin/git/etc/gitconfig`—, y el `.gitattributes` de Drupal dice
  `text eol=lf` para todo. Las dos reglas se contradicen, así que ficheros cuyo contenido es
  idéntico al del repositorio aparecen como modificados para siempre: `git diff` sale vacío para
  todos menos dos.

  **No es peligroso pero cuesta caro**, porque convierte `git status` en ruido y obliga a comprometer
  con rutas explícitas en vez de `git add -A`, que es lo que se ha hecho esa noche. El arreglo es una
  línea, `git config --local core.autocrlf false`, seguida de una pasada de `git add --renormalize .`
  en un commit propio que no lleve nada más. Se deja para un rato tranquilo, porque ese commit toca
  cincuenta ficheros y no conviene mezclarlo con trabajo de verdad.

  Y dos que **sí tienen cambio de contenido sin comprometer**: `scripts/pon-el-simbolo-que-toca.php`
  y `scripts/que-calcula-el-javascript.php` han pasado de 17.800 a 8.900 bytes cada uno, justo la
  mitad, que es lo que ocurre al convertir un fichero de UTF-16 a UTF-8. Alguien los arregló y no los
  comprometió. Hay que mirarlos y decidir, pero el cambio parece bueno.
- **La pantalla de ajustar stock no admite medias unidades.** Visto el 16 de agosto de 2026 al
  repasar todas las columnas decimales. `field_tec_quantity` del movimiento de inventario guarda
  **cuatro decimales**, pero su formulario le pone `step = '1'` a propósito, para que la ruedecita
  del navegador suba de uno en uno. El efecto secundario es que la validación de Drupal usa ese
  mismo paso, así que **un ajuste de 2,5 rollos se rechaza** con «is not a valid number», igual que
  el fallo de los factores pero por el motivo contrario: allí el paso era demasiado fino y aquí es
  demasiado grueso.

  No se ha tocado porque cambia el comportamiento de una pantalla que quizá se quiere así. La
  decisión es de una línea: o el stock se ajusta solo en unidades enteras y entonces la columna
  sobra con cuatro decimales, o hay que admitir fracciones y el paso pasa a `any` con la misma
  comprobación de decimales exacta que ya usa la ficha del material.
---

## Hecho

### 2026-08-19 (madrugada) — Cada marca ya tiene su catálogo, y editarla ya es un botón y no la única puerta

Lo pidió el dueño mirando `/b`: *cuando hago clic en las marcas, llego al edit de cada marca. Tendría
que haber un botón para ir al edit, y cuando haces clic en la casilla de la marca que te mandase a una
pantalla en la que se ven todos los productos de esa marca.* Y añadió una duda: *¿es posible que antes
de que elimináramos marcas y lo volviéramos a poner existiese una pantalla similar o me lo estoy
inventando?*

**No se lo inventaba.** Existió una vista llamada `tec_brands_gui_products_by_brand`, y su nombre sigue
fosilizado en la lista de vistas permitidas de siete campos de tipo *viewfield*, al lado de su gemela
`tec_crm_contact_customer_products`. Las dos se perdieron antes de agosto; el diario del día 14 anotó
que de marcas y patrones sobrevivían tres vistas, y son exactamente las tres que hay hoy. O sea que
esto no se ha inventado, se ha repuesto.

**La pantalla no se ha escrito de cero.** Se ha clonado `block_4` de la vista de productos, que es la
pestaña *Products* de la ficha del contacto, y solo se le ha cambiado por dónde entra: donde antes
entraba un cliente ahora entra una marca. Así el catálogo sale con las columnas que ya se conocen
—nombre, la tira de colores con sus fotos, tipo, patrón y cliente— y el día que se toque una se tocan
las dos. Se pega en la ficha del término con el mismo mecanismo que usa la ficha del producto para
enseñar sus colores, un campo *viewfield* con el valor puesto de fábrica y escondido del formulario,
así que no hay una casilla nueva que nadie deba rellenar.

**Tres trampas del clonado que no se ven leyendo el original.** La primera, la relación con el cliente
se ha quedado puesta además de la nueva con la marca, porque alguna columna del molde cuelga de ella y
quitarla habría vaciado columnas. La segunda, el molde lleva tres enlaces escritos a mano con
`raw_arguments.id` dando por hecho que el argumento es un cliente. La tercera es la que podía hacer
daño: el botón *+ Product* del molde pasa `?target_id=`, y eso **no es decoración**. El campo de cliente
del producto tiene el módulo `epp` puesto con `[current-page:query:target_id]`, o sea que rellena el
cliente con lo que venga en ese parámetro. Copiarlo tal cual habría escrito el número de una marca en
la casilla del cliente. El botón de la pantalla nueva va sin él.

**El orden del catálogo es por nombre, y es una decisión provisional.** El molde ordena por el peso que
se arrastra en *Organize products*, y ese peso está guardado por cliente: en una pantalla nueva no hay
ninguna fila, así que ordenar por él no ordenaría nada. Dar a la marca su propio orden —que el dueño ya
dijo que lo decide él y no el cliente— es el paso siguiente del modelo, el que está apuntado arriba en
*Esta semana*. También se ha quitado la columna *Brand*, que en la ficha de una marca repetiría en todas
las filas el título de la propia página.

**Y en `/b`, el botón de editar ya estaba hecho y escondido.** Alguien lo escribió con su icono y su
clase y lo puso a no dibujarse nunca, probablemente porque la baldosa entera ya llevaba a editar y
sobraba. Ahora la baldosa lleva a la ficha de la marca y el lápiz al formulario, que es lo que se pedía.
Esto separa a marcas de colores, que sigue con el comportamiento viejo, y está bien: marcas es el sitio
donde hay algo dentro que ver, colores no.

Probado en `scripts/la-marca-ensena-su-catalogo.php`, que crea dos productos en dos marcas para poder
distinguir, y comprueba las tres puertas: que la pantalla devuelve los productos de la marca que se le
pasa y ni uno más, que la ficha del término los enseña, y que en `/b` la baldosa y el lápiz van a sitios
distintos. Y la receta que lo montó está en `scripts/dar-catalogo-a-la-marca.php`, que se puede volver a
lanzar.

**Un detalle que conviene recordar de la prueba**, porque hará perder tiempo a quien no lo sepa: la
primera vez falló una sola afirmación, la de que la ficha enseña su producto, y el catálogo estaba
perfecto. Buscaba el nombre tal como lo había escrito, y el arreglo de anoche pone mayúscula en la
inicial de cada palabra al guardar. **Una prueba no debe dar por sabido el nombre de lo que crea: tiene
que preguntárselo a la ficha después de guardarla.**

### 2026-08-18 (madrugada) — Renombrar un producto no le cambiaba el nombre, y nadie podía verlo

Venía de una pregunta del dueño sobre los duplicados: *¿qué pasa con el nombre de la variación y del
producto duplicados? Recuerdo que en el pasado tuvimos problemas cuando cambiábamos el nombre de la
variación duplicada.* Se miró, y el recuerdo era bueno: el fallo existía, seguía ahí y era más ancho
de lo que él recordaba.

**En los tres pisos del árbol el nombre no se teclea, se deduce.** El del producto sale de
`field_product_name`, el del color de su término de color y el de la talla de su término de talla. Y
en los tres el campo que guarda el nombre de verdad —el `title`— **está escondido en el formulario**.
Esa es la parte que hace el fallo difícil: una ficha con dos nombres distintos no se distingue de una
sana mirándola, porque la pantalla enseña el campo y no el nombre.

**Los tres automatismos que rellenaban el nombre compartían una regla, y la regla tenía un agujero:**
actuar al nacer, o solo mientras el nombre esté vacío. Una copia nace con el nombre del original ya
puesto, así que queda fuera del alcance de esa regla **para siempre**. La variación 816 vivió meses
con el término White y el nombre Black.

El del producto era peor todavía. Su nombre se calcula **solo al nacer** y no hay ningún proceso de
actualización detrás, así que renombrar un producto separaba los dos nombres sin vuelta atrás. Se
comprobó creando uno, renombrándolo y mirando: campo `PRUEBA nombre nuevo`, ficha llamada `PRUEBA
Nombre Viejo`. Y apareció una tercera versión que nadie esperaba: **una talla que nazca con nombre
puesto tampoco mira su término**. No muerde hoy porque las tallas nacen sin nombre desde el
formulario, pero es la misma trampa esperando.

**Y se escapa fuera del producto, que es lo que lo hace caro.** El nombre de una línea de pedido se
estampa desde `[productLoaded:title]` por un camino y desde `[productLoaded:field_product_name]` por
otro. Un producto con dos nombres sale con uno en un documento y con otro en el siguiente, sin nada
que diga cuál de los dos es el de ahora. Un albarán y una proforma del mismo producto podían no
llamarse igual.

**El arreglo es el mismo sitio y la misma forma que el del color de anoche**: el guardado recalcula
los tres nombres, en cada guardado, venga la ficha de donde venga. Sustituye a tres reglas distintas
en tres procesos distintos por una sola, y deja los procesos de ECA como estaban, haciendo trabajo
repetido pero inofensivo. Al renombrar un producto se le aplica también **la mayúscula inicial en cada
palabra** que ya le ponía al crearlo, decisión del dueño, para que el catálogo no acabe escrito de dos
maneras según si el nombre es el original o uno posterior.

**No había nada que reparar.** Se barrieron las nueve fichas que existen —un producto, dos colores,
seis tallas— y ninguna tenía dos nombres. Esto se arregla antes de que muerda, y solo se ha llegado a
tiempo porque el ERP es nuevo y nadie había renombrado un producto todavía. Con trescientos productos
en marcha, encontrar cuáles se llaman mal habría sido el trabajo, no el arreglo.

Queda vigilado en el guardián, que sube a 147 comprobaciones, y probado en
`scripts/el-nombre-no-se-queda-atras.php`, que renombra un producto, un color y una talla y mira que
el nombre visible siga a los tres. Las seis afirmaciones fallaban en dos sitios antes del cambio.

### 2026-08-18 (madrugada) — Los tres botones de duplicar, probados por fin, y un código de barras que no debe viajar

El dueño duplicó a mano el color Black del guante, que es el material de prueba más completo que hay
en el sitio: tres tallas y cuatro líneas de BoM en cada una. Un clic tuvo que fabricar **dieciséis
fichas**, y las fabricó todas en seis segundos.

Salió entero. Las fracciones llegaron escritas como fracciones —`1/4`, `1/30`, `1/35`, `1/12`, con su
decimal calculado al lado— y los números normales igual. Los precios, los enlaces atados por los dos
lados, la bandera bajada. Y lo que no se ve en pantalla y era el riesgo de verdad: **las doce líneas
son nuevas, no las del original**. Si el proceso hubiera apuntado a las mismas, las dos copias se
verían idénticas y el día que alguien cambiara una cantidad en una habría cambiado la de la otra sin
enterarse.

Después se borró la copia, y se llevó sus tres tallas y sus doce líneas, y limpió su número de la
lista del producto. Es la cascada de unas horas antes, esta vez sobre datos de verdad y no sobre el
árbol de mentira de la prueba automática.

**El código de barras deja de copiarse.** Lo decidió el dueño al ver el informe, y es de cajón: un
código identifica una cosa, así que dos cosas con el mismo no identifican ninguna. Viajaba en los
tres botones —duplicar talla, duplicar color y duplicar producto—, que es lo que hace peligrosos estos
cambios: arreglar uno solo deja el fallo saliendo por los otros dos caminos, y entonces parece
aleatorio.

**Y hay una trampa en cómo se toca un proceso de ECA.** Cada uno vive en dos ficheros: `eca.eca.X`,
que es lo que se ejecuta, y `eca.model.X`, que es el dibujo que se edita en pantalla. El editor
regenera el primero desde el segundo, así que un cambio puesto solo en el ejecutable dura hasta que
alguien abra el diagrama y le dé a guardar. Sería un fallo que reaparece meses después sin que nadie
haya tocado nada. El guardián ya vigilaba esto desde el trabajo de la numeración, y ha confirmado que
los treinta procesos tienen dibujo y ejecutable de acuerdo.

**El paso no se ha quitado: se ha quedado sin nada que escribir y se llama ahora «Leave the Barcode
empty».** Quitar una caja obliga a reconectar la flecha y a mover las coordenadas del dibujo en tres
diagramas, que es trabajo delicado para nada; y una caja que dice la regla en voz alta es
documentación en el sitio donde el siguiente va a mirar. Además así es más fuerte: no es que no
copie, es que garantiza el campo vacío pase lo que pase antes.

**Fuera los dos clones apagados**, `darq39m` e `icpsbgv`, copias de la versión 1.4 de duplicar color y
duplicar producto que alguien dejó al editar la 1.5. Parecían inofensivos porque no se disparaban,
pero llevaban dentro la versión vieja de las reglas —de hecho, después del cambio del código de
barras eran los dos únicos sitios del ERP donde seguía copiándose—, así que encender uno por error era
volver meses atrás. Los procesos de ECA pasan de 32 a 30, y el despliegue al servidor bajará de 36 a
30 en vez de a 32. La historia de esos ficheros está en git, que es donde van las copias de
seguridad.

**Y el tercer botón, que no se había probado nunca.** Duplicar un producto es el mayor de los tres:
recorre colores, las tallas de cada color y el BoM de cada talla. La prueba nueva monta un producto
con dos colores, cuatro tallas y ocho líneas, pulsa la bandera y comprueba las quince fichas de la
copia una por una: que cada color cuelgue del producto nuevo y no del viejo, que cada talla apunte a
su color nuevo, que no se comparta ni una sola pieza entre los dos árboles, y que los cuatro códigos
de barras lleguen vacíos. Luego borra el producto copiado y el original y mira que no quede nada de
los dos.

- Guiones nuevos: `que-copio-el-duplicado.php`, que imprime de quién es hijo cada ficha y avisa si una
  línea de BoM cuelga de dos tallas, que es el fallo que en pantalla no se distingue de un duplicado
  correcto; `se-fue-entero-el-duplicado.php`, que borra y cuenta lo que queda; y
  `se-puede-duplicar-un-producto.php`, la prueba del tercer botón.
- Una observación medida, para cuando el catálogo crezca: un solo clic de duplicar color despertó unas
  veinte veces al proceso que reacciona al guardar una talla, porque cada campo que se pone es un
  guardado y cada guardado lo vuelve a llamar. Con tres tallas se nota en seis segundos y da igual.
  Con un producto de diez colores por ocho tallas, aquí es donde va a doler.

### 2026-08-18 — Borrar dejaba restos en tres sitios, y a uno de ellos no se llegaba desde ninguna pantalla

El árbol del producto tiene cuatro pisos —producto, color, talla y las líneas de BoM de cada talla—
y el borrado solo se llevaba el del medio.

**La rutina de ECA tiene dos acciones de borrar y las dos dicen «borrar la talla».** Así que borrar
un producto mataba las tallas de sus colores y dejaba los colores de pie: sin tallas y apuntando a un
producto que ya no contesta. Y las líneas de BoM no se las llevaba nadie, nunca. Esas son las peores,
porque a una línea de BoM solo se llega a través de su talla, de modo que en cuanto la talla
desaparece la línea no la vuelve a ver nadie desde ninguna pantalla, pero sigue ocupando su sitio en
la tabla y contando en los recuentos.

De un solo producto de prueba borrado el 14 de agosto quedaron los dos restos, y los dos aparecieron
esta semana sin buscarlos: el color 888 había sobrevivido al producto 887, y las líneas 29700 y 29701
a la talla 889. Borradas las tres cosas.

**El tercer resto es más callado y por eso más peligroso.** Drupal no quita el número del hijo de la
lista del padre cuando el hijo se borra. En pantalla no se nota, porque una vista se salta lo que no
puede cargar. Pero la rutina de borrado de ECA recorre esas listas y se para en la primera entrada
que no carga, así que basta un número muerto en medio de la lista de un color para que todas las
tallas que van detrás se salven del borrado. Un resto invisible fabricando restos nuevos.

**La cascada vive ahora en el código y está entera:** un producto se lleva sus colores, un color sus
tallas y una talla sus líneas de BoM. Y no se fía de la lista del padre: además de recorrerla,
pregunta quién apunta hacia arriba, porque un hijo que falta de la lista existe igual y sería
justamente el que dejaría atrás. La rutina de ECA sigue instalada, porque además de borrar redirige a
la lista de productos, pero ya no encuentra nada que hacer. Al borrar un hijo se le quita también su
número de la lista del padre, salvo cuando el padre se está yendo también, que es lo normal en una
cascada y no tiene sentido guardar una lista que está a punto de desaparecer.

Lo único que la cascada se niega a llevarse es una línea de BoM que otra talla esté usando. Hoy nadie
comparte líneas —duplicar una talla las copia en vez de apuntar a las mismas— pero una línea de dos
tallas no es una contradicción, y enterarse por las malas costaría un BoM de verdad.

**Y una escopeta cargada que llevaba meses en el cajón.** El guion que borra el juego de datos de
prueba hace un tercer barrido para recoger líneas sueltas, y decidía si una línea estaba suelta
preguntándole a su campo `field_tec_size_variation`. Ese campo no es el lazo con la talla: es el buzón
del importador, solo lleva algo cuando la línea entró por una importación, y está vacío en todas las
que nacen en el formulario o al duplicar, que son todas las de verdad. El guion habría leído ese vacío
como «esta línea no tiene talla» y se habría llevado por delante el BoM entero del sitio. Ahora las
dos clases de línea se preguntan igual, desde el lado del padre: ¿hay alguien que la tenga en su
lista?

**De vocabulario:** «escandallo» desaparece del proyecto y se queda «BoM», que es lo que dicen las
pantallas. Ochenta y ocho apariciones en trece guiones y en este diario. El dueño no conocía la
palabra, y una palabra que hay que explicar cada vez no sirve para nombrar nada.

- Prueba nueva: `borrar-no-deja-restos.php`, que monta un árbol entero y lo desmonta de abajo arriba.
  Dieciséis comprobaciones entre borrar una talla, un color y un producto, incluida una talla que su
  color no tiene apuntada, que es la que antes se salvaba.
- Guardián: la sección 13 pasa a seis comprobaciones. Entran que ninguna línea de BoM se quede sin
  talla, que nadie apunte hacia arriba a una ficha que ya no existe y que ninguna lista de padre
  guarde un número muerto. Las tres miran referencias rotas, que es lo que el guardián no sabía ver:
  preguntaba si un campo estaba vacío, no si apuntaba a algo que ya no está. 145 en total.

### 2026-08-17 (noche) — La talla que no sabía de quién era: un 404, un duplicado mudo y una columna que decía el número dos veces

El dueño se puso a probar a mano la cadena de ventas y producción, y en veinte minutos salieron tres
fallos que no se parecían en nada. Los tres eran el mismo.

**La columna que decía el número dos veces.** Al escribir `0.25` en «Production requires», la pantalla
enseñaba `0.2500 · 0.25`. La columna enseña el texto original al lado del número formateado cuando el
texto es una fórmula, y decidía si lo era comparando dos cadenas: `"0.2500"` no es `"0.25"`, así que
lo enseñaba siempre. Ahora la pregunta es si el texto es un número. Las fórmulas se quedan, y se
quedan a propósito: en un documento de corte, `1 / (1/12)` da doce y `1 / 0.0833` da 12,004802.

**El 404 al guardar.** Reordenar las líneas del BoM y guardar aterrizaba en `/tec_product/`, sin
número detrás de la barra. El lápiz de editar construye esa dirección con `field_tec_product`, el
atajo que cada color y cada talla guardan directo a su producto, y estaba vacío.

**El duplicado que no duplicaba.** El icono de copiar una talla no hacía nada y se convertía en
«Unflag this item». Ese icono no es un botón, es una bandera: al pulsarla un proceso de ECA la
escucha, fabrica la copia y baja la bandera él mismo, y bajarla es el paso seis. El proceso arrancó
cuatro veces y murió dos en el paso dos y dos en el tres, que es donde carga el producto y el color de
la talla. Los dos campos estaban vacíos.

**Por qué no se oyó nada, que es lo importante de esta noche.** ECA le pide permiso a una acción antes
de ejecutarla, y la acción que carga una referencia responde *acceso denegado* cuando no hay nada que
cargar. No una excepción, no un aviso: denegado. Así que ECA corta la rama y no escribe una línea. En
el registro quedaron cuatro arranques y nada más. Esa forma de fallar no es de duplicar: es de las
once cosas de este ERP que funcionan con banderas.

**Y por qué los campos estaban vacíos.** Los rellenaban procesos que escuchan solo el `insert` y solo
actúan si el enlace al padre ya está puesto en ese instante. Una talla nacida dentro del formulario
anidado se guarda antes que el color que la va a contener, así que pierde la ventana y nadie vuelve a
por ella. Las dos tallas del único producto del sitio llevaban así desde que se crearon.

Ahora se preguntan dos veces. El guardado de la propia ficha lo pregunta otra vez, sin depender del
orden en que se creen las cosas; y el guardado del padre ata a todos los hijos que le falte el enlace,
que es el primer momento en que la respuesta existe. Con eso, duplicar funciona: la copia se lleva el
color, el producto, el precio, el código de barras y el BoM entero, con sus cantidades y con el texto
tal como se escribió —el `1/12` sigue siendo `1/12` en la copia, no un `0,0833`.

- Guiones: `quien-se-quedo-sin-producto.php` para mirar y `nadie-pierde-el-hilo-con-su-producto.php`
  para arreglar, con ensayo en seco y relanzable.
- Pruebas nuevas: `la-talla-no-pierde-su-producto.php` y `se-puede-duplicar-una-talla.php`, esta
  última pulsando la bandera de verdad y creando las fichas en el orden real, hijo primero y padre
  después.
- Guardián: sección 13 nueva, y una sección 14 que vigila que ninguna bandera de las que hacen de
  botón se quede puesta en reposo. Una puesta significa siempre lo mismo, que su proceso empezó y no
  llegó al final, y hasta ahora eso no se veía por ningún sitio. Vigila diez: las siete que son
  botones y las tres cerraduras internas.
- De paso: el color 888 fuera, y las marcas dejan de contarse como dato fijo y pasan a contarse como
  contenido, porque el dueño crea marcas nuevas y el campo es obligatorio para dar de alta un
  producto.

### 2026-08-17 (tarde) — Un pedido de compra tiene un estado, y las cuatro pantallas lo dicen igual

Nueve peticiones del dueño después de estrenar el control de compras. Cuatro eran de acabado, una
era un fallo de cuentas y las otras cuatro eran, en realidad, la misma: **el estado de un pedido no
significaba lo mismo en dos pantallas seguidas**.

**Los días estaban mal por uno, todos los días.** `KJ 26-001` se hizo el 14 y el 17 decía 2. La resta
mezclaba la medianoche de hoy con la hora exacta en que se creó el pedido: del 14 a las once de la
mañana al 17 a las cero horas hay dos días y trece horas, y el suelo se quedaba con el 2. Ahora los
dos extremos bajan a su medianoche antes de restar, que es lo que cualquiera entiende por «cuántos
días lleva esto». Se redondea en vez de truncar, por si algún día esto corre donde cambian la hora.

**El estado, que era el fondo del asunto.** Un pedido servido entero y esperando la factura decía
*Closed* en `/supplier-orders`, *Closed* en su ficha, *Closed* en el impreso y *Delivered* en la
pantalla de control. Ninguna mentía: las tres primeras enseñaban la bandera de abierto o cerrado, que
responde a otra pregunta. Pero quien miraba dos pantallas obtenía dos respuestas, y ese es el tipo de
cosa que hace que la gente deje de fiarse de una pantalla y vuelva a su hoja de cálculo.

Ahora hay una sola función, `Purchasing::progress()`, y siete lecturas:

| Lectura | De dónde sale |
|---|---|
| Open | nadie ha dicho nada todavía |
| On process / Ready to pick up / On the way | lo único que teclea una persona |
| Delivered | todas las líneas recibidas |
| Closed | recibido entero **y** la factura archivada |
| Cancelled | cerrado sin que llegara nada |

Las dos mitades hacen falta para *Closed*, por lo mismo que la cola no suelta un pedido a medio
servir aunque llegue su factura.

**Y *Cancelled* no es solo «nada pendiente y nada recibido».** Leído desde las líneas, un borrador que
alguien empezó esta mañana es idéntico a un pedido que se dio por perdido. Lo que los separa es la
bandera de abierto o cerrado, y esa es la razón por la que esa bandera sigue mereciendo la pena, junto
con los botones, que la siguen y deben seguirla: si un pedido se puede editar es otra pregunta.

**Open dejó de venir de fábrica.** El campo nació con «On process» de valor por defecto y el guion de
encendido lo sembró además en los once pedidos que ya existían. Las dos cosas se deshicieron al día
siguiente: un pedido hecho esta mañana no lo ha perseguido nadie, y una pantalla donde todas las filas
ya afirman que alguien está en ello es una pantalla donde el día que alguien esté de verdad no se
nota. No se añadió ningún valor para decirlo; Open es el hueco.

**El desplegable ya no es un desplegable.** La columna se lee por color y un `<select>` nativo no pinta
sus opciones igual en dos navegadores: Safari las ignora. Así que la pastilla y su lista son elementos
nuestros, con el teclado escrito a mano, que es lo que un `<select>` regala. Los colores viven en una
sola hoja compartida por las cuatro pantallas. Y rotaron como pidió el dueño: *On process* naranja,
*Ready to pick up* amarillo, *On the way* azul.

**Fuera el botón de guardar.** Cada celda se manda sola en cuanto se toca, igual que las casillas de
`/stock`. Lo que contesta el servidor no es un «recibido» sino el estado recalculado: si mientras la
página estaba abierta alguien recibió la mercancía, la fila se corrige sola en vez de seguir ofreciendo
una opción que ya no existe.

**Lo demás:** columna *Delivery* a la derecha de *Status*, que es la única que sabe decir «ha llegado
la mitad»; el nombre del proveedor lleva a su ficha, que es donde está el teléfono; y un botón de
recibir en las filas donde se puede recibir. En las demás no sale: se le pregunta a la propia ruta en
vez de repetir sus reglas, porque un botón que lleva a un «no puedes» es peor que ningún botón.

**Un fallo que se llevó por delante la ficha entera, y solo lo vio la prueba.** Al convertir el bloque
del estado en la ficha, se le copió la configuración del anterior tal cual. Un `field_block` declara
dos contextos y un `extra_field_block` solo uno, así que el `view_mode` sobrante tiraba
`/tec_order/943` con «Assigned contexts were not satisfied». Nunca llegó al navegador porque la prueba
de punta a punta pide la ficha. Vigilado desde entonces en la sección 12.

- Guiones: `el-estado-empieza-en-open.php` y `el-estado-se-lee-igual-en-todas-partes.php`, los dos con
  ensayo en seco y los dos relanzables.
- La prueba `lo-que-esta-de-camino-se-ve.php` pasó de cinco casos a siete: entraron el borrador
  abierto sin cantidades y el cerrado sin nada recibido, que son los dos que se leen igual desde las
  líneas y significan cosas distintas.
- Guardián: sección 12, nueve comprobaciones. Y una corrección en el recuento de ficheros, que se
  ponía en rojo porque abrir la ventanita de subir factura y cerrarla deja un fichero temporal
  detrás; ahora cuenta los permanentes e informa de los que esperan al barrido.

### 2026-08-17 — Control de compras: la hoja de Google se muda al ERP

Quien vigila los pedidos de compra lo llevaba en una hoja de Google al lado del ERP. Copiaba a mano
cada pedido después de crearlo aquí, movía un color según lo que le contara el proveedor por teléfono,
y borraba la fila cuando la mercancía llegaba a la fábrica. Dos listas de los mismos pedidos, y **la
que la gente miraba de verdad era la que el ERP no conocía**.

Ahora está en `/supplier-orders/queue`, con la misma pinta que la hoja —cabecera verde oscuro, filas
alternas, pastillas de color— porque quien la va a usar lleva años leyendo esa hoja cada mañana y una
pantalla que se le parece se usa el primer día sin que nadie la enseñe.

**De todo lo que había en la hoja, al ERP solo hubo que añadirle tres datos.** El resto ya lo sabía:
el número del pedido, el proveedor, quién lo creó y cuándo. Los tres nuevos son dónde está la
mercancía, qué día dijo el proveedor que llegaría, y la factura.

Y dos columnas **no se guardan en ninguna parte**, se deducen:

- La **fecha de llegada** sale de los movimientos de almacén que escribió la recepción, que ya apuntan
  a la línea de la que vinieron y llevan su fecha. Guardarla otra vez en el pedido sería dejar que las
  dos discreparan, y la copia del pedido es la que nadie puede comprobar.
- Los **días** son una resta.

**El cuarto estado de la hoja, «Delivered», tampoco se guarda, y esa fue la decisión de fondo.** El
dueño pidió que se pusiera solo al recibir la mercancía, y la forma más obvia era que la recepción lo
escribiera. Pero entonces habría un desplegable con «Delivered» dentro, y alguien podría marcarlo sin
que entrase una sola unidad en el stock: la lista de la compra dejaría de reponer un material que
nunca vino. Así que el campo guarda **tres** estados —los tres que una persona sí sabe y el ERP no
puede adivinar— y la pantalla lee el cuarto de las líneas, como el resto de cosas derivadas de este
módulo. Sale solo, no se puede teclear, y no hizo falta tocar el formulario de recibir.

**Qué sale en la pantalla, en dos reglas.** Un pedido está mientras haya algo que perseguir: nada
pendiente y nada recibido nunca significa que no hay nada que perseguir —un pedido a medio escribir sin
cantidades, o uno cerrado corto antes de que saliera nada—, así que no aparece, esté abierto o cerrado.
Eso se lleva por delante los **trece pedidos vacíos** que llevan semanas ensuciando `/supplier-orders`
sin que haya que decidir todavía qué hacer con ellos. Y se va cuando se archiva la factura, que es la
regla del dueño: la mercancía llegó hace semanas, pero el papeleo es lo que lo termina.

A esa segunda regla se le puso **una condición que el dueño no pidió**: solo se va si además ha llegado
todo. Las facturas llegan a fin de mes, así que en la práctica vienen después de la mercancía; pero el
día que un proveedor facture por delante de una entrega parcial, dejar que el papel esconda un pedido
que todavía debe material es exactamente cómo se olvida la mitad que falta. Cuando pasa, la ventanita
lo avisa al subir la factura.

Lo demás, por orden de importancia:

- **Se ordena por las nueve columnas, incluidas las dos que no existen en la base de datos.** Por eso
  la pantalla es un formulario propio y no una vista de Views, en contra de lo que se dijo al
  planificarlo: Views ordena por SQL, y los días y la fecha de llegada no están en SQL. Justo la
  columna que dice a quién hay que llamar primero sería la única que no se podría ordenar. Se ordena en
  PHP, que sale barato mientras la lista sean las decenas de pedidos en vuelo y no el histórico. Y de
  paso es como están hechas las otras pantallas de control de esta casa: `/o/queue`, `/stock`,
  `/purchase` y recibir.
- **Los huecos se hunden siempre**, se ordene hacia arriba o hacia abajo. Un pedido sin fecha prometida
  no es «el más temprano» ni «el más tardío», es el que nadie ha concretado.
- **La factura va al almacén privado**, no al público. Lleva precios y número fiscal de un tercero, y
  en el público se la descarga cualquiera que acierte la dirección. Se sube en una ventanita, acepta
  PDF y fotos, y **no fuerza la cámara** a propósito: con `capture` el móvil abre directo la cámara y
  ya no hay forma de adjuntar el PDF que uno tiene guardado. Ofreciendo las dos, la misma pantalla vale
  para el escritorio y para la puerta de descarga.
- **La puerta es el menú principal, no una pestaña.** Las pestañas son la respuesta obvia y en este
  tema no se ven: DXPR saca la tira de pestañas del flujo y la tira encima de la cabecera pegajosa, y
  el bloque que las pinta está limitado al rol de administrador. Ya pasó con el botón de recibir.
- Permiso propio, `access tec purchase queue`, para *manager* y *executive*. Compras no es producción.
- **Los tres campos van escondidos en la ficha del pedido y en el PDF.** Son notas internas de
  seguimiento; el papel que se le manda al proveedor no cambia. El guardián lo vigila.
- Al teclear en la tabla y pinchar luego una cabecera para ordenar, el navegador **avisa antes de
  perder lo escrito**. Ordenar es un enlace y se lleva la página por delante.

Ficheros: `PurchaseQueueForm`, `InvoiceUploadForm`, `Purchasing::deliveredOn()`,
`css/purchase-queue.css`, `js/purchase-queue.js`.
`scripts/crear-los-campos-de-la-cola-de-compras.php` abre los tres huecos.
**Al desplegar hay que correr `scripts/encender-el-control-de-compras.php --aplicar`**, que da el
permiso y siembra el estado de partida de los pedidos que ya existen: el valor por defecto solo vale
para los nuevos, y sin eso los de hoy abrirían con el desplegable en blanco.
Prueba: `scripts/lo-que-esta-de-camino-se-ve.php`, con los cinco casos que se dan de verdad.
Guardián: sección 11, trece comprobaciones. **130 de 130.**

Queda pendiente, y es corto: **esconder las líneas sin cantidad en el formulario de recibir**, que el
dueño apartó a propósito para no mezclarlo con esto.

### 2026-08-17 — Un número entregado no vuelve

Lo preguntó el dueño con un caso concreto: si borro `POLYTE 26-012` y creo otro pedido ahora mismo,
¿qué número le toca? La respuesta era `POLYTE 26-012` otra vez, y esa es la respuesta equivocada. El
proveedor puede tener el primero guardado en PDF, y dos documentos distintos con el mismo número dejan
de ser un número.

Pasaba porque el contador no se guardaba en ninguna parte: se deducía contando los pedidos que existían
de ese contacto y ese año, y sumando uno. Contar tiene dos defectos, y el segundo es peor que el
primero. Uno, que al borrar la cuenta baja, así que el número vuelve a estar libre. Y dos, que **una
cuenta no es un máximo**: con huecos en la serie, la cuenta podía aterrizar dentro de uno y repartir un
número de mitad de año. Con `001, 002, 003, 009` la cuenta da cuatro, el candidato es el `005`, está
libre, y ahí se paraba.

Ahora el número más alto entregado se **recuerda**, por contacto y año, en un almacén de clave y valor
—`tec_production.order_number`—, y ese apunte solo sube. Se leen dos cosas y gana la mayor: lo apuntado
y lo que de verdad esté en uso. La segunda no sobra: siembra a la primera, de modo que no hubo nada que
migrar, y cura el contador si alguien renombra o importa pedidos por detrás. Ninguna de las dos puede
hacerlo retroceder.

El bucle que sube hasta encontrar un nombre libre **se queda**. El contador responde a «¿esto se ha
entregado ya?» y el bucle a «¿este nombre está libre ahora mismo?», que son preguntas distintas: un
título escrito a mano puede ocupar un número que el contador nunca repartió.

Un detalle de diseño que conviene saber: **pedir un número lo gasta**. No hay forma de asomarse al
siguiente sin tomarlo, a propósito, porque una vista previa que no gastara se repartiría dos veces en
cuanto dos pantallas la enseñaran. Quien quiera enseñar un número, que cree el pedido y lea su título.
Está escrito en el propio método para que nadie lo llame por curiosidad, y por eso el guardián comprueba
el apunte leyendo, no pidiendo.

- La clase es la de siempre, `OrderNumber`, y sigue siendo el único sitio donde se decide un número.
- `scripts/el-numero-gastado-no-vuelve.php` siembra el apunte desde lo que ya hay. En rigor no hacía
  falta, porque se siembra solo; hace falta para un caso: si se borran **todos** los pedidos de un
  proveedor antes de que su contador exista, no queda de dónde deducir y la serie volvería a empezar en
  `001`. Solo sube y se puede repetir. **Al desplegar en el servidor hay que correrlo con `--aplicar`**,
  porque esto vive en la base de datos y no viaja con la configuración.
- La prueba `scripts/se-numeran-los-pedidos.php` gana la sección que importa: se crea un pedido, se
  borra, y el siguiente no hereda su número; y con todos borrados tampoco empieza de cero. De paso se le
  quitó una asignatura pendiente: esperaba `KJ 26-004` porque KJ tenía tres pedidos el día que se
  escribió, y KJ tiene dieciséis, así que llevaba tiempo fallando por un hecho del mundo y no del código.
  Ahora comprueba lo que de verdad importa, que dos seguidos van uno detrás de otro. Y **fotografía los
  contadores al empezar y los devuelve al acabar**, porque desde hoy cada pedido que crea gasta un número
  de un proveedor real y borrarlo ya no lo devuelve.
- Guardián: dos comprobaciones nuevas, que el número entregado se apunta y que el apunte va por delante
  de lo repartido en todas las series. 117 de 117.

### 2026-08-16 (noche) — «Algo se ha roto»: había dos calculadoras y solo una se enteró del cambio

El dueño abrió un borrador de compra y encontró esto:

```
1   PA-55 H 20 mm    [ 5 ]   Sheet   ฿ 799,70   ฿ 0,00      <- mal
2   PA-55 H 18 mm    [ 1 ]   Sheet   ฿ 719,73   ฿ 719,73    <- bien
                                     Subtotal   ฿ 4.718,23  <- bien, y son las dos líneas
                                     VAT 7%     ฿ 330,28    <- bien
                                     Total      ฿ 0,00      <- mal
```

Lo raro era la mezcla: el subtotal de abajo valía 4.718,23, que es justo 799,70 × 5 + 719,73 × 1, o sea
que **alguien había calculado bien la fila que arriba salía a cero**. Esa contradicción solo se explica
si hay dos manos escribiendo en la misma celda, y las había.

El borrador de compra cargaba dos calculadoras a la vez. Una es la del inyector
`draft_for_ace_company_view`, que es la que se ha ido tocando estos días y la que dibuja el pie con el
IVA. La otra vivía en `admin_form_styles`, colgada de `/po/draft/` desde hacía meses, y hacía lo mismo:
recorría las filas, multiplicaba y escribía el importe. Mientras las dos dijeron lo mismo, nadie tenía
por qué notar que estaban las dos.

Dejaron de decir lo mismo por el cambio de esa misma tarde, el de congelar el precio: el borrador cambió
la columna del coste del material por el precio de la propia línea. La segunda calculadora seguía
buscando `td.views-field-field-tec-cost`, no encontraba nada, `parseMoney('')` devolvía cero, y escribía
`฿ 0,00` encima del importe que la primera acababa de dejar bien.

Que solo se estropeara la fila 1 y el `Total` es el detalle que lo confirma: la segunda se dispara al
teclear, y al teclear reescribe **la fila que se toca** y **el total general**, nada más. El dueño estaba
escribiendo en la fila 1. Las líneas `Subtotal` y `VAT` no las conoce, y por eso quedaron bien.

Nada llegó a la base de datos. El pedido 865 tenía sus 10 y sus 10 con 7.997,00 y 7.197,30 guardados
mientras la pantalla decía cero, y el guardián confirma que las 53 líneas de compra siguen valiendo su
precio por su cantidad. La calculadora sobrante sabía escribir en un campo oculto que sí se guardaba,
pero esa columna se quitó del borrador el día que se eliminó el `Sub total` editable, así que llevaba
semanas sin nada donde escribir.

- Borrada entera: `js/purchase-order-calculator.js`, su librería y el enganche en
  `admin_form_styles_page_attachments`, donde queda escrito por qué no debe volver. No se ha puesto nada
  en su lugar: el script inyectado ya sumaba las filas y dibuja el pie, y además sirve a los dos
  borradores, el de compra y el de venta.
- De paso, `una-sola-suma-en-el-borrador.php` quita del script que queda el selector de reserva
  `.views-field-field-tec-cost`, que era la misma trampa dormida: ninguna de las diecisiete pantallas de
  línea enseña ya esa columna.
- Guardián: dos comprobaciones nuevas que leen **todo** el javascript del sitio, el de los módulos y el
  de los inyectores. Que ninguno nombre una columna que no existe, y que **una sola cosa** escriba los
  importes del borrador. 115 de 115.

La lección no es del selector, es del duplicado. Dos trozos de código haciendo la misma cuenta sobre las
mismas celdas no dan la cara mientras coinciden; dan la cara meses después, cuando uno de los dos se
actualiza y el otro no, y entonces el fallo no se parece a su causa.

### 2026-08-16 (noche) — El pie de la ficha cae debajo del Sub total

Quedaba dicho como alternativa y el dueño la eligió en cuanto vio las tres pantallas en una tabla. Las
cifras del pie —`Subtotal`, `VAT`, `Total`— se pegan al borde derecho de la tabla, así que caen bajo la
**última columna, sea cual sea**. Con `Received` y su marca de *closed short* al final, caían bajo una
columna que no habla de dinero, y el ojo tenía que saltar de una punta a otra para comprobar que la suma
de la derecha era la suma de esa otra columna.

```
antes   ... | Quantity | Purchase UoM | Purchase Cost | Sub total | Received | (marca)
ahora   ... | Quantity | Received | (marca) | Purchase UoM | Purchase Cost | Sub total
```

No hay forma honesta de alinear una tabla con una columna que no sea la última, así que la manera de que
el pie cuadre con el `Sub total` es que el `Sub total` sea el último. `Received` se va detrás de
`Quantity`, que además es donde mejor se lee: cuánto se pidió y cuánto ha llegado, una al lado de la
otra.

Esto deshace parte de lo que se decidió unas horas antes, cuando esas dos columnas se pusieron al final
por ser lo que se mira cuando llega la mercancía. El razonamiento no era malo; lo que no se había visto
es que arrastraban el pie con ellas.

- Hecho por `scripts/el-pie-cae-debajo-del-sub-total.php`. `las-dos-pantallas-de-compra-se-parecen.php`
  queda actualizado para que repetirlo no las devuelva al final.
- Guardián: la comprobación de que las dos pantallas se llaman igual ahora **quita** `Received` y su
  marca antes de comparar, en vez de dar por hecho que van al final; y hay dos nuevas, que van pegadas a
  `Quantity` y que el `Sub total` es el último. 113 de 113.

### 2026-08-16 (noche) — Un pedido emitido vale lo que valía el día que se hizo

El dueño lo planteó como un cuento de tres fechas, y era exactamente el caso que rompía:

> 1 de enero, pedido 001, cien unidades a 10 → 1.000. El 1 de febrero el proveedor sube el material de
> 10 a 15. El 1 de marzo, pedido 002, cien unidades → 1.500. **El 5 de marzo entro en el 001: ¿qué
> veo?**

Hasta anoche veía 15 y 1.500. Y no como un fallo de pantalla: **el importe guardado también cambiaba**.
La ECA que calcula el total de una línea de compra multiplicaba la cantidad por el coste que tuviera el
material *en ese momento*, y lo hace en cada guardado. Cualquier cosa que tocara la línea reescribía el
precio de un pedido cerrado, y **recibir mercancía guarda la línea**: bastaba con que llegara el camión
en abril para que el pedido de enero cambiara de precio solo, sin que nadie lo tocara.

#### Lo curioso es que el propio dibujo ya daba por hecho lo contrario

La ECA no calcula nada si la línea no tiene precio propio: hay una condición puesta a la entrada que lo
comprueba. Alguien montó la puerta para el precio de la línea y luego, dentro, multiplicó por otra cosa.
Y **ventas nunca tuvo el problema**: `process_lvy385w` siempre multiplicó por `[entity:field_tec_price]`.
Compras era la única de las dos que miraba al material, lo que encaja con lo que el dueño ya sospechaba
de este módulo: copiado de ventas y dejado a medias.

#### El fallo de origen, que estaba una casilla más atrás

Al mirar los datos apareció algo peor que la deriva. **Veintinueve líneas llevaban escrito un precio que
no era un precio de compra**: 0,02 en un material que se compra a 80, 0,30 en uno de 45. No eran restos
raros, eran exactos: 80 ÷ 4000 y 45 ÷ 150, los dos factores de conversión de cada material.

El culpable es `process_kryibry`, el que crea el pedido desde la ficha de un proveedor. Estampaba en la
línea `[inventoryItem:field_tec_price]`, y en un material **`field_tec_price` es el coste por unidad de
consumo**, no el de compra —el de compra es `field_tec_cost`—. El paso se llamaba, literalmente, *Set
Sales price* dentro de un flujo de compras. Copia y pega de ventas sin releer.

Nadie lo había notado porque el importe se recalculaba desde el material y tapaba el disparate. En
cuanto el precio de la línea pasa a mandar, ese pedido de 800 habría pasado a valer 2 baht al siguiente
guardado. **Arreglar solo la multiplicación habría sido peor que no tocar nada.**

#### Lo que se ha hecho

- **La cuenta** (`process_fpvka81`) multiplica por `[entity:field_tec_price:value]`, como ventas.
- **El nacimiento** (`process_kryibry`) estampa `[inventoryItem:field_tec_cost]`, el de compra. El paso
  pasa a llamarse *Set Purchase cost*. La lista de la compra, que crea sus líneas por PHP y no por ECA,
  ya lo hacía bien.
- **Los dos dibujos**, además de los dos ejecutables. Si alguien abre el modelador y guarda, el
  ejecutable se regenera desde el dibujo: dejarlo con el token viejo es dejar la trampa rearmada.
- **Las dos pantallas.** *Purchase Cost* lee el precio de la línea y ya no el coste de hoy del material.
  El *Sub total* de la ficha deja de ser una multiplicación de la propia vista y pasa a ser el importe
  guardado, que es lo que ya leían el impreso y el pie. Esto cierra el cabo que quedó abierto en la
  entrada de abajo: las tres pantallas de compra leen ahora los mismos dos campos.
- De paso se fue una columna oculta del borrador, una multiplicación cuya fórmula entera era
  `@field_tec_cost` y a la que no miraba nadie.

#### Las líneas viejas, arregladas según lo que hubiera de donde tirar

`scripts/arreglar-los-precios-de-las-lineas.php`, con copia de seguridad delante y pasada en seco antes:

- **Con importe ya enseñado (7 líneas):** el precio pasa a ser importe ÷ cantidad. Se respeta el dinero
  que el pedido lleva enseñando desde que se hizo, que es lo único que se firmó y lo único que vio el
  proveedor. Las siete cayeron **exactas** en el coste de compra del material, que es la confirmación
  más limpia de que el campo guardaba el coste de consumo y nada más.
- **Sin importe (22 líneas):** borradores a medio escribir, sin cantidad y sin nada enseñado nunca. Se
  les pone el coste de compra de hoy, que es el que se aplicaría si alguien los terminara esta tarde.
- **18 ya estaban bien**, y **ningún pedido cambió de valor**.

#### Cómo se sabe que sigue así

- `scripts/el-precio-no-se-mueve.php` recorre el cuento del dueño con sus mismas cifras, incluida la
  llegada de la mercancía en abril, y comprueba las tres pantallas y el pie. El material de la prueba
  tiene un coste de consumo cien veces menor que el de compra a propósito: si alguien vuelve a
  confundirlos, se ve en la primera cifra.
- Guardián, sección 10: los dos tokens, los dos dibujos, la lista de la compra, que ninguna pantalla de
  compra lea ya el coste de hoy, y —lo que de verdad importa— que **todas las líneas guardadas valgan su
  precio por su cantidad**. Una sola que no cuadre significa que algo ha vuelto a escribir importes por
  su cuenta. 111 de 111.

**El impreso.** Se quedó fuera de esta tanda con sus nombres de siempre, y el dueño lo pidió igualado
al verlo en la tabla comparativa. Está en la entrada siguiente.

### 2026-08-16 (noche) — El impreso del pedido, también

Quedaba la tercera manera de escribir lo mismo. El borrador y la ficha ya se habían igualado; el papel
que sale al proveedor seguía con `Qty.`, `Price` y `Unit`, y —lo que se ve menos y confunde más— con
**otro orden**: la cantidad delante del material y el precio delante de la unidad. Eran dos cambios de
sitio, no solo tres rótulos, y eso solo se vio al poner las tres pantallas en una tabla:

```
antes   Item | Picture | Qty. | Material name | Price | Unit | Sub total
ahora   Item | Picture | Material name | Quantity | Purchase UoM | Purchase Cost | Sub total
```

No gana ni pierde ninguna columna: son las mismas siete, dichas igual y puestas donde las otras dos. De
paso, `Purchase Cost` sale alineado a la derecha; estaba sin alinear, así que en la misma tabla el
precio caía a la izquierda y el `Sub total` a la derecha.

`Purchase UoM` se queda **sin** alinear, que es como está en las otras dos. Centrarlo en el papel
quedaría mejor, pero la gracia de todo esto es que las tres se lean igual, no que el impreso vaya por su
cuenta aunque sea para mejor.

- Hecho por `scripts/el-impreso-se-lee-como-el-borrador.php`. Antes de mover nada comprueba que ninguna
  columna acabe leyendo a otra que quede por detrás: Views solo deja a un campo leer los que se
  declararon antes que él, y cuando eso se rompe **no revienta** —sale la columna en blanco y nadie se
  entera hasta que lo ve un proveedor—. Es lo que pasaría con `Picture`, que se dibuja leyendo las dos
  columnas ocultas de la imagen.
- Guardián: una comprobación más en la sección 9, que compara el impreso entero contra el borrador,
  columna a columna. 112 de 112.

### 2026-08-16 (cierre) — El borrador y la ficha de compra dejan de escribir lo mismo de dos maneras

El dueño pidió cuatro arreglos de aspecto y salieron seis, porque al ir a igualar las dos pantallas
apareció que **no eran la misma cosa escrita igual**. La misma columna se llamaba *Unit of Purchase
(UoP)* en el borrador y *Unit* en la ficha; el coste era *Cost by UoP* y *Cost per UoP*; la cantidad,
*Quantity* y *Qty.* Quien mira las dos seguidas tiene que traducir, y traducir es donde se equivoca uno.

Las dos leen ahora `Item | Picture | Material name | Quantity | Purchase UoM | Purchase Cost | Sub
total`, y la ficha sigue con `Received` y su marca de *closed short*. Esas dos van al final porque son
lo que se mira cuando llega la mercancía, no mientras se lee el pedido.

#### El dinero, que fue lo único que no era obvio

El símbolo del baht no lo pone la vista: lo lleva el propio campo en su definición y la vista solo lo
respeta. Pero **Sub total en la ficha no es un campo**, es una cuenta que hace la propia vista
—`@field_tec_quantity * @field_tec_cost`—, así que ahí hay que escribirlo a mano. Salía `3,998.5` al
lado de un `฿ 799.70`, con la columna llamándose igual en la otra pantalla. Ahora las dos dicen
`฿ 3,998.50`. De paso, al coste del borrador le faltaba la coma de los millares.

**Y una cosa que conviene saber y no se ha tocado.** Eso hace de la ficha la única pantalla que
recalcula el total de la línea a partir del coste de hoy, en vez de leer el total que ECA le estampó.
El pie suma los estampados. Hoy coinciden; el día que alguien corrija el coste de compra de un
material después de haber emitido un pedido, **la columna y el pie de la misma página dirían cosas
distintas**. Arreglarlo es cambiar la ficha para que use el mismo campo que las otras dos pantallas, y
no se ha hecho porque el dueño pidió expresamente ir de uno en uno.

> Cerrado esa misma noche, y era más gordo de lo que pone aquí: no es que la ficha *enseñara* otra
> cifra, es que el importe **guardado** también se reescribía. Ver la entrada de arriba, «Un pedido
> emitido vale lo que valía el día que se hizo».

#### Los dos arreglos del borrador

- La casilla de la cantidad ocupaba el ancho entero de su celda y era lo más gordo de la fila para
  meter cuatro cifras. Pasa a `5rem`, algo menos de un tercio.
- Encima de cada casilla ponía *Quantity*, que ya lo dice la cabecera. La regla que lo tapaba
  **existía desde siempre** y solo cogía la pantalla de ventas: Views le pone un `-1` detrás a la
  segunda copia de un campo, y la de compras es la segunda.

#### El pie, alineado como el del borrador

En el borrador las tres líneas son filas dentro de la tabla, así que ocupan todo el ancho y la cifra
cae en la última columna. El de la ficha era una cajita pegada a la derecha. Ahora ocupa el ancho
entero, con el rótulo pegado a la cifra y la cifra al borde, y usa el relleno de celda del propio tema
(`--bs-table-cell-padding-x`) para que acabe donde acaba la columna de arriba.

Con `Received` al final, las cifras del pie caen bajo esa columna y no bajo *Sub total*. Si se
prefiere lo segundo, la manera es poner `Received` **antes** de *Sub total*; no hay forma honesta de
alinear una tabla con otra columna que no sea la última.

> El dueño eligió lo segundo esa misma noche. Ver «El pie de la ficha cae debajo del Sub total».

- Hecho por `scripts/las-dos-pantallas-de-compra-se-parecen.php`, que se niega a reordenar en vez de
  adivinar si la pantalla ha ganado o perdido una columna desde que se escribió la lista.
- Guardián: tres comprobaciones nuevas en la sección 9. 103 de 103.

### 2026-08-16 (cierre) — El pie del pedido de compra: base, IVA y lo que de verdad se paga

El dueño lo vio él solo: creó un pedido a POLYTEC, proveedor tailandés registrado, y dijo que **no
veía el IVA por ningún lado**. Tenía razón a medias, que es lo interesante. El 7% **sí** estaba
guardado en el pedido —lo estampa el gancho desde el trabajo anterior— pero estaba oculto en la
pantalla, y ninguna de las tres sumas de compra lo tenía en cuenta. Las tres sumaban las líneas y
llamaban **Total** al resultado. Con un proveedor tailandés eso no es lo que se paga, así que el
número de la pantalla y el de la factura no podían coincidir nunca.

#### De dónde salía cada total, que no era de donde parecía

Lo primero fue averiguarlo, porque las tres pantallas lo hacían distinto y solo una era javascript:

| Pantalla | Quién ponía el total | Qué sumaba |
|---|---|---|
| Ficha del pedido (`/tec_order/838`) | `attachment_1` de la vista, con `group_type: sum` | las líneas, en SQL |
| Impreso (`po/%/print`) | el mismo `attachment_1` | lo mismo |
| Borrador (`po/draft/%`) | el javascript inyectado, en el navegador | precio por cantidad, fila a fila |

Y una trampa que decidió el diseño: **`attachment_1` lo comparten ventas y compras**. Sirve también
al bloque del pedido de venta y a la proforma. Meter el IVA ahí habría puesto una línea de IVA en
las proformas de todos los clientes, que es exactamente lo que no se puede hacer todavía.

#### Lo que se ha hecho

Las dos pantallas que pinta el servidor dejan de usar ese trozo compartido y terminan en un pie
propio, dibujado por **un solo manejador escrito una vez**,
`src/Plugin/views/area/VatTotals.php`. Es un *area* y no una columna porque habla del pedido, no de
una línea, y debe salir una vez abajo pase lo que pase con las filas. La cuenta la hace
`Vat::breakdown()`, que es también a quien preguntan la prueba y el guardián: tres respuestas
libres de separarse es justo el fallo que no interesa tener.

`attachment_1` sigue sirviendo a `block_1` y a `page_4` exactamente igual que antes. **Ventas no se
ha enterado de nada**, y eso está comprobado en la prueba y en el guardián.

#### Tres casos, porque se ven distintos

- **Con porcentaje.** `Subtotal`, `VAT 7%`, `Total`.
- **Con un cero estampado** —proveedor de fuera, o tailandés sin registrar—. Las mismas tres líneas,
  con el IVA diciendo `VAT 0%`. Un cero explicado se defiende; un hueco donde debería haber una
  cifra, no. Es el mismo criterio que se usará en la proforma.
- **Sin porcentaje ninguno** —los dieciséis pedidos anteriores a todo esto—. El `Total` pelado de
  siempre. No se les inventa un tipo ahora: poner una línea de IVA en un papel ya enviado a un
  proveedor es peor que no ponerla.

#### El borrador es otra cosa, y tenía que serlo

`po/draft/%` es un formulario, y la gracia es que las cifras se muevan **según se teclean las
cantidades**, antes de guardar nada. Así que ahí el pie lo suma el navegador. El problema es que el
javascript no tenía manera de saber el 7: el porcentaje está en el pedido, no en ninguna de las
filas que tiene delante. Se lo pasa `tec_production_views_pre_render()`, y solo cuando el pedido es
de compra y trae porcentaje.

Esa guarda no es adorno: **el mismo javascript corre en el borrador de ventas**. Pregunta si hay
porcentaje y, cuando no lo hay, dibuja el `Grand Total` de una sola línea que dibujaba antes. Así
compras gana el IVA y ventas sigue igual hasta que le toque.

El IVA se redondea al satang **antes** de sumarlo, en el navegador exactamente igual que en
`Vat::on()`. Sumar primero y redondear después deja el borrador y el impreso separados por una
moneda camino del proveedor.

#### Un detalle que costó dos intentos en la prueba

La prueba creaba líneas con precio 100 y 50 y esperaba 1.000. Salió 9.356,49. La razón es que **una
línea de compra no usa su propio precio**: ECA le pone el total a partir del coste de compra del
material, y el precio escrito a mano se ignora. La prueba pasó a leer lo que quedó guardado en vez
de dar por hecho lo que pensaba haber escrito, que además es lo correcto: lo que hay que comprobar
es que el pie diga lo mismo que las filas de encima.

- Construido por `scripts/poner-el-iva-en-el-pie.php` (la vista) y
  `scripts/el-borrador-suma-el-iva.php` (el javascript). Los dos se pueden pasar dos veces.
- Prueba: `scripts/se-suma-el-iva.php` — un proveedor tailandés y uno extranjero, las dos pantallas
  leídas como las lee una persona, el porcentaje llegando al borrador de compra y no al de ventas,
  y la proforma demostrada intacta.
- Guardián: sección 9, siete comprobaciones. 100 de 100.

#### Lo que falta, y a propósito

**Ventas.** Está confirmado con el dueño que ANV está registrada en Tailandia y vende a tailandeses
y a extranjeros, y que el tipo de la venta debe salir del país del cliente, estamparse al crear el
pedido y poder corregirse después. Es el mismo mecanismo, pero **la decisión no es la misma**: en
compras la pregunta es si el proveedor cobra IVA, y en ventas es si a ese cliente hay que
cobrárselo. Se hace por separado para no arrastrar una respuesta a una pregunta distinta.

### 2026-08-16 (noche) — El país se deseleccionaba solo: la culpa era del apaño, no del país

El dueño estaba dando de alta un cliente tailandés y contó que **al elegir el país la pantalla
saltaba y el país se quedaba sin marcar**. Pasaba en `/admin/content/tec_crm/add/tec_contact_organization`.

#### Lo que no era

El registro estaba limpio: ni un error de PHP a esa hora. Y el servidor hacía su trabajo bien, dos
veces comprobado: primero con una subpetición interna y después **por HTTP de verdad, atravesando
Apache**, que es lo único que reproduce lo que ve el navegador. En las dos, Tailandia volvía marcada
y aparecían los campos tailandeses. La estructura de la página también estaba sana: un solo
selector de país, un solo formulario, los dos javascript cargados.

#### Lo que era

El módulo Address trae de fábrica un ajax en el selector de país: cambias de país y se rehace solo
el recuadro de la dirección, porque cada país pide campos distintos —Tailandia quiere provincia,
Singapur no—. **Ese ajax estaba arrancado a mano.** En su lugar había un botón escondido y un
javascript nuestro que, en cuanto el selector lanzaba un `change`, **enviaba el formulario entero y
recargaba la página**.

Con **257 países en la lista**, eso es inservible. Un selector nativo dispara `change` con cada
tecla y con cada vuelta de rueda del ratón: el dueño no llegaba a Tailandia, la página se le iba a
medio elegir. De ahí el salto, y de ahí que pareciera deseleccionarse solo.

#### Por qué estaba arrancado, y por qué ya no hace falta

El comentario del código lo explicaba: el ajax **tiraba el proceso de Apache** en esta máquina
Windows, con un `STATUS_ACCESS_VIOLATION`. Era verdad en su día. Se volvió a preguntar: **diez
cambios de país seguidos por HTTP real, con cinco países distintos incluido Tailandia**, todos con
respuesta correcta y ninguna caída.

Lo más probable es que la caída ya la arreglara `tec_crm_ux_address_neutralize_group_keys()`, que se
escribió a la vez y quita unas claves `#group` numéricas que Address usa para maquetar y que el
Form API confunde con agrupaciones de verdad, corrompiendo el árbol del ajax. **Esa función se
queda**; apagar el ajax encima era cinturón y tirantes.

#### Lo que se ha ido

- El botón escondido `tec_crm_ux_address_rebuild` y su manejador de envío.
- `tec_crm_ux_address_disable_ajax()`, que era quien arrancaba el `#ajax`.
- El fichero `js/crm-address.js` y la biblioteca `tec_crm_ux/address` que lo cargaba.

Ahora se elige el país y **no se recarga nada**: se cambia solo el recuadro de la dirección.

#### Que no se vuelva a apagar sin que nadie se entere

- `scripts/se-elige-el-pais.php` comprueba, en las dos clases de ficha, que el selector lleva su
  ajax, que apunta al recuadro correcto, que no ha vuelto el botón escondido y que la lista trae los
  257 países. Y comprueba lo que de verdad importa: que con Tailandia sale provincia y con Singapur
  no.
- El guardián tiene una sección 8 con lo mismo en corto. Quedó en **93 de 93**.

### 2026-08-16 — El mismo fallo, un campo más allá: los factores de conversión

Cuatro horas después de dar por cerrado lo del coste de consumo, el dueño estaba editando el
material `khun` y no podía guardar. Esta vez el error decía **«Inventory to Consumption Factor is
not a valid number»**, y el número era **10000**. Un rollo que da diez mil centímetros.

#### La regla de verdad, que la entrada de esta misma mañana contaba mal

La comprobación rota de Drupal es `Number::validStep()`, eso estaba bien visto. Pero el margen que
acepta no es `paso / 2^52` sino **`paso / 2^24`**, y lo que importa no es el número de decimales por
sí solo: es que **el resto que mide crece con el tamaño del valor mientras el margen se queda
quieto**. De ahí sale una frontera limpia y fácil de recordar:

> El formulario rechaza cualquier cifra que llegue a **10^(9 − decimales)**.

| Decimales | Techo | Dónde |
|---|---|---|
| 2 | 10.000.000 | costes, stock, ROP — nadie lo pisa |
| 4 | 100.000 | cantidad de movimiento, volumen |
| 5 | **10.000** | los dos factores de conversión |
| 6 | **1.000** | coste de consumo |

Así que la frase de esta mañana —«el coste de compra, que sí se teclea, conserva su paso de 0,01»—
era cierta pero por poco: ese campo también tiene techo, solo que está en diez millones.

#### Y lo grave: nos lo hicimos nosotros

Los dos factores tenían **dos decimales** hasta que el propio dueño pidió cinco, el 15 de agosto,
porque dos no bastaban para un factor de 0,4. Ese cambio bajó su techo de diez millones a diez mil.

Lo que nadie vio venir es que **no hace falta tocar una ficha para romperla**. El 10000 de `khun` ya
estaba guardado desde antes, y el formulario revalida el campo en cada guardado, así que el material
quedó congelado en el momento del cambio de escala: no se le podía cambiar ni el nombre. Un barrido
con `scripts/quien-esta-bloqueado.php` encontró **tres de cinco materiales** en esa situación, dos
por el coste de consumo —ya rodeados— y uno por el factor.

El dueño tenía además otro material a punto de meter con el factor en **300000**, treinta veces por
encima del techo. Habría dado el mismo error el primer día.

#### El arreglo, esta vez sin lista de campos

El rodeo de la mañana nombraba dos campos, y por eso no cubrió estos. Ahora
`_admin_form_styles_fix_decimal_steps()` **recorre el formulario entero**: toda casilla que sea un
número respaldado por una columna decimal recibe `step = any`, su escala en `data-tec-decimals` y,
si es de las que se teclean, una comprobación propia.

Esa comprobación es la pieza que faltaba. Quitar el paso sin más habría dejado pasar `0,123456789`
a una columna de cinco decimales, que lo redondea al entrar **sin decir nada**. Así que la regla que
el paso pretendía defender se comprueba de verdad, contando los dígitos detrás del punto en el texto
que se tecleó: es la misma regla —un decimal es múltiplo de su paso exactamente cuando cabe en la
escala— decidida sobre los caracteres en vez de sobre una división que no puede representarlos.

Los dos costes derivados quedan fuera de esa comprobación a propósito, y ahí sí hace falta la lista:
el servidor los recalcula al guardar, así que juzgar lo que deje el navegador solo podría frenar un
guardado por una cifra que iba a ser sustituida.

#### Comprobado

- **`scripts/se-guarda-el-material.php`**, ampliado con los dos materiales reales: 1 rollo en 10.000
  cm y 1 rollo en 300.000 cm. Los dos entran, con su coste por centímetro bien dividido
  (0,016923 y 0,000564). Y una prueba en negativo: un factor con seis decimales en una columna de
  cinco **sigue rechazándose**, ahora con un mensaje que dice qué pasa —«holds at most 5
  decimals»— en vez de «is not a valid number».
- **Guardián**: ya no lista escalas a mano, que es lo que dejó escapar este fallo. Construye la
  ficha del material de verdad y mira casilla por casilla que ninguna se quede con el paso roto y
  que las que se teclean lleven la comprobación, así que **un campo decimal nuevo queda cubierto sin
  tocar el guardián**. Y una cifra informativa: cuántas fichas de todo el ERP solo se pueden guardar
  gracias al rodeo. Hoy son tres.
- **La página exacta del error**, `/taxonomy/term/3278/edit`, pedida como la pide un navegador:
  el factor sale con `value=10000.00000 step=any decimales=5`.

#### La lección

Cambiar la escala de una columna **no es un cambio de formato**. Mueve un techo invisible que puede
quedar por debajo de datos que ya existen, y las fichas afectadas no dan señal hasta que alguien
intenta guardarlas. La próxima vez que se toquen decimales, pasar `scripts/quien-esta-bloqueado.php`
detrás.

### 2026-08-16 — «Consumption Cost is not a valid number»: no era el dato, era Drupal

El dueño creó un proveedor nuevo, empezó a meter una materia prima suya, le dio a guardar y le salió
ese error. El número del que se quejaba era **799,700000**.

#### Lo que pasaba

El coste de consumo guarda **seis decimales**, así que Drupal validaba el campo contra un paso de una
millonésima. Y esa comprobación suya, `Number::validStep()`, no aguanta pasos tan pequeños: divide el
valor entre el paso y compara el resto en coma flotante contra un margen de `paso / 2^52` —unos
2e-22— cuando el resto que la doble precisión deja de verdad ronda 2e-13.

El resultado es una frontera absurda:

| Valor | Paso 0,01 | Paso 0,00001 | Paso 0,000001 |
|---|---|---|---|
| 100,700000 | pasa | pasa | pasa |
| 799,700000 | pasa | pasa | **rechazado** |
| 1.000,700000 | pasa | pasa | **rechazado** |
| 1.611,500000 | pasa | pasa | **rechazado** |

O sea que **cualquier coste de consumo por encima de unos cien con decimales era irrechazable**. Y por
eso no había aparecido antes: el material de pruebas costaba céntimos. Apareció el primer día que
alguien puso el precio de un rollo de tela de verdad.

#### El arreglo

Los dos costes derivados —inventario y consumo— **no se escriben nunca a mano**: los divide
`tec_inventory_taxonomy_term_presave()` a partir del coste de compra y los dos factores, en cada
guardado. Así que lo que el navegador deje en esas dos casillas se descarta y se vuelve a calcular.

Es decir que esa validación estaba juzgando un número que iba a ser sustituido de todas formas. Solo
podía dar **falsas alarmas**. Se le pone el paso en `any` a las dos, y se acabó. Lo que la columna
admite lo sigue decidiendo su precisión y su escala, y el redondeo se sigue haciendo en un solo sitio.

El coste de compra, que sí se teclea, conserva su paso de 0,01. Ahí la comprobación funciona y sirve.

> **Corregido esa misma tarde.** Dos cosas de esta entrada no se sostienen: el margen es `paso /
> 2^24` y no `paso / 2^52`, y el coste de compra **no** se quedó con su paso, porque también tiene
> techo —diez millones— y porque nombrar campos de uno en uno fue justo lo que dejó escapar el
> siguiente caso. La regla buena es que el formulario rechaza a partir de `10^(9 − decimales)`. Ver
> la entrada de arriba, «El mismo fallo, un campo más allá».

De paso hubo que avisar al calculador del navegador: sacaba los decimales leyendo el atributo `step`,
y sin paso que leer habría escrito la división en crudo, `266.66666666666663`. Ahora recibe la escala
en un atributo aparte y el `step` queda como respaldo.

#### Comprobado

- **`scripts/se-guarda-el-material.php`**: pide el formulario y lo envía de vuelta como hace el
  navegador, porque el fallo entero vivía en la validación del formulario y llamar a la API de
  entidades habría pasado de largo sin enterarse. Guarda el material de la pantalla del dueño (799,70
  con factores 1 y 1), uno que no divide limpio (800 entre 3 y luego entre 7, que da 38,095238) y uno
  de cinco cifras. Los tres entran.
- **Guardián, dos comprobaciones nuevas**: que el rodeo siga puesto mientras Drupal siga rechazando
  799,700000 —si algún día lo arreglan, la comprobación lo dice y se puede quitar el rodeo— y que no
  aparezca **ningún campo nuevo de seis decimales** sin el mismo tratamiento, porque caería en la
  misma trampa y no se sabría hasta que un dato pasara de cien.

#### Dos trampas del propio guion de prueba, que valen para el siguiente

Enviar un formulario desde un guion tiene dos minas y las dos dan síntomas engañosos:

1. **`Request::create()` no interpreta los corchetes.** Un campo se llama `name[0][value]` y PHP lo
   convierte en arrays anidados antes de que la petición llegue a Symfony; pasándolo a mano se queda
   como una clave literal con corchetes, Drupal no encuentra nada donde mira, y contesta que **todos
   los campos obligatorios están vacíos**. Se lee como un formulario roto y es un guion roto. Se
   arregla dando la vuelta por `parse_str(http_build_query(...))`.
2. **Un formulario que guarda lanza la redirección.** Dentro de una subpetición Drupal entrega el
   `EnforcedResponseException` en lugar de devolver la respuesta. O sea que **el éxito llega como una
   excepción**. Hay que recogerla.

### 2026-08-16 — Los ajustes de la empresa tienen por fin una pantalla

El IVA nació donde nacen estas cosas: en un fichero de configuración, tocable solo por línea de
comandos. Que es perfecto para quien construye el ERP y no le sirve de nada a quien lleva la empresa,
que es justo la persona que se entera de que el tipo ha cambiado.

No estaba solo. De los once ajustes de `tec_production.settings`, únicamente tres tenían dónde
tocarse —el panel de capacidad de `/o/queue`—. Los **cinco nodos de los iconos de la portada** llevaban
igual de huérfanos desde que se montaron.

#### Lo que se ha hecho

- **`/admin/config/tec/company`**, con el porcentaje y los cinco iconos. Los iconos por autocompletado
  de nombre: la configuración sigue guardando el número del nodo, pero nadie tiene que saberlo.
- **Permiso propio, `administer tec company settings`**, para `tec_manager` y `tec_executive`. Pedir el
  de configurar el sitio entero habría significado entregar el ERP completo para poder cambiar un número.
- **Enlace bajo *VAT treatment*** en la ficha de cada proveedor, leyendo el porcentaje **en vivo**: «VAT
  is currently 7% — change it». Ahí es donde surge la pregunta.
- **El esquema de configuración que el módulo nunca tuvo.** No había ni carpeta `config/schema`. Daba
  igual mientras nadie escribiera esos ajustes desde un formulario; un `ConfigFormBase` sobre
  configuración sin esquema protesta, y Drupal 11 es más estricto que el 10. Las once claves descritas.

#### Por qué en Configuración y no junto a las otras pantallas de `/admin/tec`

Era lo primero que se pensó, por coherencia. Pero **esas pantallas no están en ningún menú**: son rutas
sueltas de vistas, sin entrada en ninguna parte. Colgar ahí un enlace de menú habría dado una miga de
pan que no lleva a ningún sitio. Bajo Configuración la jerarquía existe, es donde un administrador
mira por instinto, y la sección `/admin/config/tec` deja sitio a lo que venga después —moneda,
dirección de la empresa—.

#### Lo que no se movió, a propósito

Los tres ajustes de capacidad se quedan en la pantalla de la cola. Se leen al lado de las cifras que
mueven, y archivarlos detrás de un menú habría quedado más ordenado y peor.

#### Un detalle que evita un fallo futuro

La correspondencia entre cada ajuste de icono y la pantalla que abre estaba escrita dentro del
suscriptor que hace la redirección. Ahora es una constante pública que **usan los dos**: el suscriptor
para redirigir y el formulario para construirse, etiquetando cada fila con el título de la propia
pantalla. Dos copias de esa lista se habrían separado, y esa avería se manifiesta como un icono que
abre la pantalla equivocada sin decir nada.

#### Comprobado

- **`scripts/se-tocan-los-ajustes.php`**: las tres maneras de que esto deje de funcionar en silencio
  —que la página deje de cargar, que el permiso deje de cerrar, que guardar deje de escribir— más que
  guardar **no desenganche los cinco iconos** y que el enlace de la ficha cite el porcentaje vigente y
  no uno escrito a mano. Deja el porcentaje como lo encontró, así que se puede pasar en producción.
- **Guardián, cinco comprobaciones más** en la sección 7. Van **88**, 0 mal.
- **Prueba de humo**: 58 pantallas, con las dos nuevas dentro.

### 2026-08-16 (cierre) — El IVA: un dato por proveedor, y el porcentaje en un solo sitio

El dueño lo planteó como un problema de tres casos: los proveedores de fuera de Tailandia no llevan
IVA, los de dentro llevan un 7%, y **algunos pequeños de dentro tampoco lo llevan** porque van por
otro régimen y no emiten factura con IVA. Y preguntó si la salida era poner una casilla en la ficha de
cada proveedor y rellenarla a mano.

#### Los tres casos son una sola pregunta

Puesto así parece una regla por países con una excepción pegada, que es la clase de cosa que envejece
mal. Pero los tres casos contestan a lo mismo: **si ese proveedor cobra IVA**. El de fuera no, el
tailandés pequeño tampoco, el tailandés registrado sí. Así que no hay regla de países: hay **un dato
por proveedor**, y se rellena una vez.

Eso es lo que se ha hecho, con dos matices sobre la casilla que se proponía.

**No es una casilla, es una lista de tres.** Dos de las tres respuestas dan el mismo cero, pero por
motivos distintos, y el motivo vale la pena guardarlo: un tailandés pequeño puede registrarse el año
que viene, uno de fuera no lo hará nunca, y con una casilla marcada o sin marcar dentro de un año
nadie sabría distinguirlos. Además, teniéndolo así se puede sacar la lista de los pequeños y
preguntarles.

**Y no se rellena a mano entera.** El campo de dirección es el del módulo Address, que guarda el
código de país y no una línea de texto, así que se puede sembrar de golpe: dirección en Tailandia,
«cobra IVA»; dirección fuera, «extranjero». Lo que queda a mano es solo repasar los tailandeses y
marcar los pequeños, que son unos pocos. Una tarde de revisión, no meter datos desde cero.

#### Y el porcentaje no va en la ficha

El 7 no está en ningún proveedor: está en `vat_rate`, dentro de `tec_production.settings`, una vez.
El día que el país lo cambie se toca un número y no trescientas fichas. **Qué proveedores lo cobran**
y **cuánto es** son dos preguntas distintas y ahora viven en dos sitios distintos, que es lo que
permite contestar a la segunda sin volver a hacer la primera.

#### Lo que se descartó, y por qué

Sacarlo del número de identificación fiscal era tentador, porque un negocio tailandés registrado lo
tiene y uno sin registrar no, y el campo ya está en la ficha. Se descartó por los dos extremos: un
proveedor extranjero puede tener ahí el número de su propio país, y eso inventaría un 7% que nadie
está cobrando; y un proveedor registrado al que nunca se le escribió el número perdería el IVA sin
que se note. Para dinero, un dato que alguien ha afirmado vale más que un dato deducido.

#### Lo que se ha hecho

- **`modules/custom/tec_production/src/Vat.php`**, clase nueva con todo dentro: las tres respuestas, el
  porcentaje, la decisión para un proveedor dado, y la cuenta. Se redondea ahí y solo ahí, para que una
  pantalla, un pedido impreso y lo que venga después no puedan redondear cada uno a su manera y
  discrepar en un satang.
- **`field_tec_vat_treatment`** en las fichas de contacto, dentro del recuadro *Supplier purchase
  defaults*, en **organización y persona**: aquí un proveedor a veces es una persona, y moneda,
  incoterm y forma de pago ya estaban en las dos. Añadido a la lista de campos que se esconden cuando
  el contacto no es proveedor.
- **`field_tec_vat_rate`** en el pedido de compra, escrito por el gancho de guardado al crearlo y
  editable después para un caso suelto.
- **El sembrado**, `scripts/sembrar-el-iva-por-pais.php`, que informa y no toca nada hasta que se le
  pasa `--de-verdad`, respeta las fichas ya rellenadas, y acaba imprimiendo la lista de tailandeses
  para repasar.

#### Por qué el pedido se queda con su porcentaje

Se copia al pedido en vez de consultarlo al proveedor cada vez que se enseña, por el mismo motivo que
el número: **un pedido impreso en marzo tiene que seguir diciendo en diciembre lo que decía en marzo**,
pase lo que pase entretanto con el porcentaje o con el registro del proveedor. Si el proveedor deja de
cobrar IVA en octubre, los pedidos de antes no se reescriben. Eso está probado explícitamente.

Y solo en compras. Qué IVA se le cobra al cliente es otra conversación y no se resuelve copiando esta.

#### Una ficha en blanco cobra IVA

Mientras nadie rellene el campo, el sistema supone que ese proveedor cobra. Equivocarse en esa
dirección se ve: alguien mira un pedido de compra, ve un IVA que no debería estar y lo dice. Al revés
es un número que falta en un documento, y eso no lo nota nadie hasta que lo nota el contable, meses
después.

#### Comprobado

- **`scripts/se-pone-el-iva.php`**: crea un proveedor de cada tipo, mira con qué porcentaje sale su
  pedido, y luego hace que el proveedor deje de cobrar IVA para demostrar que el pedido ya emitido no
  se mueve. También que un porcentaje escrito a mano no se sobreescribe, que la cuenta redondea al
  satang, y que el campo está en compras y no en ventas. Catorce comprobaciones, todas bien.
- **Guardián, sección 7 nueva**, seis comprobaciones: que el porcentaje siga en un solo ajuste, que las
  tres respuestas sigan siendo tres, que el campo esté en compras y no en ventas, y que sea el gancho
  de guardado quien lo pone y no una ECA. Van **81** comprobaciones, 0 mal.
- **Prueba de humo**: las 56 pantallas siguen cargando.

#### El estado del sembrado ahora mismo

Cero. Hay **un solo proveedor** en el sistema, `KJ`, y no tiene dirección, así que no hay país del que
deducir nada y su ficha se ha quedado en blanco. La maquinaria está puesta y probada para cuando entren
los proveedores de verdad; ese día se pasa el guion y queda hecho.

Los **16 pedidos de compra que ya existían** no tienen porcentaje, porque se crearon antes de que el
campo existiera. El guardián lo cuenta como dato y no como fallo. No se les ha inventado uno.

#### Lo que falta, y a propósito

**Ninguna pantalla enseña todavía base, IVA y total.** El porcentaje está oculto en todas las vistas
del pedido, porque una línea suelta que diga `VAT %: 7.00` no es lo que nadie quiere leer: las tres
cifras van juntas en un bloque de totales, y eso es el trabajo siguiente.

> Hecho ese mismo día, ver *El pie del pedido de compra: base, IVA y lo que de verdad se paga*. El
> porcentaje sigue oculto como campo suelto, que era lo correcto; las tres cifras salen juntas al pie.

#### Dos cambios que aparecieron en la exportación y no son de nadie

Al exportar salieron tocadas dos pantallas de pedido de compra que este trabajo no pretendía tocar.
Las dos se miraron antes de dejarlas pasar y ninguna cambia comportamiento:

- En la pantalla del PDF aparecieron dos banderas, *borrar pedido* y *cancelar pedido de venta*, dentro
  de `content`. Drupal ya las metía ahí en memoria cada vez que cargaba esa pantalla, así que lo único
  nuevo es que ahora está escrito. No salen en el papel porque esa pantalla usa Display Suite con
  regiones nombradas y esas dos no están en ninguna.
- En la pantalla del pedido, `items_per_page` del bloque de líneas pasó de `none` a `0`. La vista no
  pagina —su paginador es `type: none`—, así que «lo que diga la vista» y «todas» son la misma cosa.

### 2026-08-16 (noche) — Un número por pedido, y uno solo: `KJ 26-001`

El dueño lo pidió con un ejemplo de tres líneas: cliente A pide y sale `A 26-001`, cliente B pide y
sale `B 26-001` —no 002—, y cuando A vuelve, `A 26-002`. Código corto del contacto, espacio, año de dos
cifras, y un contador **por contacto** que se reinicia cada enero. Lo mismo en compras y en ventas.

#### Lo que había

Tres sitios fabricaban números de pedido y **los tres contaban distinto**:

| Quién | Qué producía | Qué contaba |
|---|---|---|
| `PurchaseListForm::nextOrderNumber()` | `26-4` | pedidos de compra por prefijo, de todos los proveedores juntos |
| ECA `process_kryibry` (compras) | `26-4` | filas de la vista `tec_order_eca_count_orders:po_count` |
| ECA `process_sclj26d` (ventas) | `A 26-001` | lo mismo por `so_count` |

Solo el de ventas conocía el código corto y los tres ceros. Y tenía tres agujeros, los dos primeros
compartidos con el de compras:

1. **La cuenta solo veía pedidos publicados**, y los dos flujos crean el pedido con `published: false`.
   Un borrador no contaba nunca, así que el segundo pedido de un cliente recibía el número que ya
   llevaba el primero. Dos pedidos con el mismo número no son una numeración.
2. **Nadie comprobaba que el nombre estuviera libre.** Borrabas un pedido y su número volvía a
   repartirse, con el primero ya impreso y enviado al proveedor.
3. **No había filtro de año.** El `26-` del nombre venía de la fecha, pero el contador de detrás no se
   reiniciaba en enero: el primer pedido de A en 2027 habría sido `A 27-004`.

#### Lo que se ha hecho

- **`modules/custom/tec_production/src/OrderNumber.php`**, clase nueva y único sitio donde se decide un
  nombre. No dentro de `Purchasing`, que está documentada como la aritmética del tablero y la lista de
  compra: la numeración de ventas no es eso.
- **`hook_tec_order_presave()`** en `tec_production.module`. Solo al crear. Renombrar después cambiaría
  lo que dice un papel ya enviado, y el número existe justo para que las dos partes citen el mismo.
- **El contador cuenta nombres, no pedidos.** Cuenta los títulos que ya empiezan por `KJ 26-`, suma uno,
  y luego **sube hasta que el nombre está libre de verdad**. Contar nombres ata el número a lo que se ha
  repartido, así que sobrevive a que un pedido cambie de manos o le corrijan la fecha, y se reinicia
  solo porque el año forma parte de lo que se cuenta. La subida es lo que lo hace seguro y no solo
  probable.
- **Los borradores (`tec_draft_order`) no gastan número**, a propósito: son un sitio de paso que se tira,
  y gastar un número en uno dejaría un hueco en la serie de un cliente que nadie sabría explicar.
- **`PurchaseListForm` ya no fabrica títulos.** Ni pasa uno: lo pone el gancho.
- **Los cuatro pedidos que existían, renumerados** por `scripts/renumerar-los-pedidos.php`, por contacto
  y por año, tomando el año de la fecha de creación de cada pedido y no de hoy:
  `26-1` → `KJ 26-001`, `26-2` → `KJ 26-002`, `26-3` → `KJ 26-003`, `PRUEBA pedido de venta` → `PRUEBA 26-001`.
- **El código corto, también en las fichas de persona.** Esta mañana quedó solo en las de organización, y
  parecía completo porque los dos contactos del sistema son organizaciones. No lo era: el selector de
  cliente de un pedido de venta (`tec_crm_references:entity_reference_3`) filtra por **tipo de contacto**,
  no por clase de ficha, así que una persona marcada como Customer es perfectamente elegible y el primer
  pedido a una persona habría nacido sin código y sin campo donde ponerlo.

#### La trampa del orden de los pasos

Los dos flujos de ECA **creaban el pedido, lo guardaban, y solo después le pegaban el cliente o el
proveedor**. Daba igual mientras el nombre llegaba más tarde. Ahora el nombre se decide en el primer
guardado, así que todos los pedidos habrían nacido **sin código**. Los dos procesos van ahora
crear → pegar contacto → guardar. El guardián vigila ese orden, porque es de esas cosas que alguien
deshace sin querer ordenando un diagrama.

#### La mina: la numeración estaba a un clic de desaparecer

Un proceso de ECA vive en **dos** objetos de configuración. `eca.eca.X` es lo que se ejecuta; `eca.model.X`
es el dibujo BPMN que muestra el editor visual. **El editor regenera el primero desde el segundo cada vez
que alguien pulsa guardar.**

El código corto y los tres ceros existían **solo en el fichero ejecutable**. El dibujo seguía diciendo
`[current-date:custom:y]-[soCount]` y no tenía ni rastro de los pasos de relleno. Abrir ese proceso en el
editor y pulsar guardar habría devuelto los pedidos de venta a `26-1`, sin aviso y sin dejar huella.

Por eso `scripts/quitar-la-numeracion-de-eca.php` opera **el dibujo** y luego se lo entrega al propio
modeller de ECA, que regenera el ejecutable a partir de él. Los dos coinciden ahora, que es el único
estado que no se podre. Verificado además que volver a guardar el dibujo **no cambia nada**, que el XML
sigue siendo válido, que no hay ninguna línea ni forma suelta, y que la pantalla del editor carga.

#### Guardián: sección 6 nueva, doce comprobaciones

- Todos los pedidos con el formato `CÓDIGO AA-NNN`.
- Ningún número repartido dos veces.
- Ningún pedido sin el código de su contacto en el nombre. **Esa es la que caza el orden de los pasos**:
  un pedido cuyo contacto tiene código pero cuyo nombre no lo lleva solo puede salir de que el contacto
  se pegara después del primer guardado.
- El código corto en las dos clases de ficha, y el de proveedor sin resucitar.
- Ninguna de las dos ECA pone el título **del pedido** (siguen poniendo el de sus líneas, que es otra
  cosa y tiene que quedarse; la primera versión de la comprobación no distinguía y dio un falso rojo).
- **El dibujo dice lo mismo que el ejecutable, en los treinta y dos procesos**, comparando los pasos que
  conoce cada fichero. Es la comprobación general de la mina, y es la que habría cazado esto sin que
  hiciera falta ir a mirar. Los treinta y dos están de acuerdo.
- La lista de la compra no se ha vuelto a hacer su propio contador.

#### Daño colateral que apareció por el camino, y estaba de verdad roto

El guardián contaba **tres redirecciones donde debía haber dos**. Tirando del hilo: la ficha 33 había
perdido su dirección bonita y se llamaba `/33` en lugar de `/customer/33`.

El patrón de direcciones era `/[tec_crm:field_tec_contact_type:0]/[tec_crm:id]`. Ese tipo de token **no
lee el campo, lo renderiza**, usando la vista `default` de la ficha — y esa vista tiene **Layout Builder
encendido**. Con Layout Builder puesto, los campos listados en la vista no son lo que se pinta; se pintan
las secciones. Así que el token salía vacío y un patrón de «/tipo/id» se quedaba en «/id».

Nadie se queja cuando pasa: la dirección sigue siendo única porque lleva el id, el sitio funciona, y no
se nota hasta que se guarda una ficha y su dirección vieja se convierte en una redirección. Arreglado
leyendo el nombre del término directamente (`:0:entity:name`), regeneradas las direcciones de las dos
fichas y borrada la redirección que dejó la dirección rota. **El patrón de los materiales usa el mismo
tipo de token**; hoy funciona porque los términos no usan Layout Builder, pero es la misma trampa.

#### También corregido

La comprobación «ningún pedido abierto sin nada pendiente» daba rojo por `KJ 26-002` y `KJ 26-003`, que
no tienen cantidades. Y no está mal: la bandera de la ficha del proveedor crea justo eso, un pedido con
una línea por material y las cantidades en blanco para que alguien las escriba. Meter esos en el mismo
saco que un pedido ya servido dejaba el guardián en rojo para siempre por algo correcto. Ahora solo
salta si el pedido **tiene cantidades** y no queda nada pendiente, y los de a medio escribir se informan
aparte.

#### Cómo quedó

- Guardián: **74 de 74**.
- `scripts/se-numeran-los-pedidos.php`: todo bien, incluido el caso A/B/A palabra por palabra y los dos
  caminos que crean pedidos.
- `scripts/funciona-la-recepcion.php`: todo bien. Hubo que tocarla: ya no puede elegir cómo se llama el
  pedido que crea, así que lee el nombre que le ha tocado.
- `scripts/cargan-las-paginas.php`: 58 páginas, ninguna con problemas.
- Copia previa: `backups/actatec_antes_de_la_numeracion_2026-08-16.sql.gz`.

#### Queda dicho

`views.view.tec_order_eca_count_orders` es configuración muerta: no la usa nada. Se deja porque borrarla
dejaría una referencia colgando en la lista de ajustes de un viewfield, pero **no debe volver a
conectarse**: sus displays `so_count` y `po_count` llevan dentro el filtro de solo publicados que causó
los números repetidos.

### 2026-08-16 (tarde) — «¿Dónde están los estados?»: en ningún sitio, y por eso no se veían

El dueño preguntó por los estados de los pedidos de compra y dijo que no era capaz de verlos. No era
cosa de no encontrarlos: **no estaban**.

`/supplier-orders` tenía una columna titulada **Order status** vacía en todas las filas, y no por
falta de datos. Preguntaba por `field_tec_order_status`, que es el estado de los pedidos de **venta**.
Un pedido de compra no tiene ese campo, y la pantalla está filtrada a pedidos de compra, así que la
columna salía vacía el cien por cien de las veces desde el día que se hizo. El mismo campo inexistente
alimentaba el condicional de los botones de la fila, así que **el lápiz de editar no aparecía nunca**,
y el enlace que había detrás apuntaba a `/o/draft`, el borrador de un pedido de venta, que tampoco era
el sitio. Tres síntomas y una sola causa: ese display se copió de una lista de pedidos de venta y
nadie terminó de cambiarle las preguntas. Estaba anotado como deuda técnica la noche anterior con un
«probablemente no sale nunca»; era un «no sale nunca», y ya está cobrado.

#### Lo que se ha hecho

- **La columna lee `field_tec_po_status`**, y hay una segunda copia escondida, `field_tec_po_status_1`,
  para el condicional. Hace falta porque views_conditional compara con lo que el campo *pinta*, y el
  visible pinta `<div class="Open">Open</div>` y no `Open`. Es el mismo truco que ya usaba el bloque
  del CRM del proveedor, que era el único sitio donde esto funcionaba bien.
- **Vuelve el lápiz** en los pedidos abiertos, apuntando a `/po/draft/{id}` con la propia lista como
  destino, y de paso llega el de imprimir, que en esta pantalla no había. Cerrado conserva imprimir y
  pierde editar, igual que en la ficha y en el bloque del proveedor.
- **Columna nueva, `Delivery`**, en `/supplier-orders` y en la pestaña de pedidos de la ficha del
  proveedor: *Nothing received*, *Partially received*, *Fully received*, *Cancelled*. Existe porque
  `Open` a secas no le dice a quien persigue a los proveedores a quién perseguir: un pedido del que no
  ha llegado nada y otro del que ya está la mitad en la estantería se leían igual.
- **No hay campo detrás ni columna en la base.** Es un manejador de vista propio,
  `ReceiptState.php`, que llama a `Purchasing::receiptState()` por fila: la misma función que usan el
  tablero y el guardián, para que las pantallas no puedan contradecirse. No añade nada a la consulta y
  no se puede ordenar por ella. Sus etiquetas de caché incluyen las **líneas** y no solo el pedido,
  porque guardar una línea invalida la línea, y sin eso la palabra se quedaría vieja en el caché.
- Solo se ha tocado el display por defecto; los tres bloques tienen su propia lista de campos. El
  estilo sí es compartido, así que ahí **solo se ha añadido**: quitar la entrada del estado de ventas
  le habría estropeado la alineación al bloque de pedidos de venta.

#### Y el segundo hallazgo del día, que era más gordo

Preguntó también si debajo de **Nothing more expected** tenía que haber una casilla. Sí, y estaba: en
el HTML, con su `<input type="checkbox">` y su etiqueta. Lo que no estaba era **visible**.

Bootstrap 5 no dibuja la casilla del navegador, dibuja la suya: `.form-check-input` pone
`appearance: none` y la pinta con `--bs-border-color` sobre un relleno de `--bs-body-bg`. DXPR apunta
`--bs-border-color` a su `--dxt-color-graylighter`, que en la paleta de este sitio anda por `#ededed`,
y `--bs-body-bg` al fondo de la página, que es blanco. **Todas las casillas del sitio eran un cuadrado
blanco de trece píxeles con un borde que no se ve**, incluidas las del tablero de stock con las que se
decide qué se compra. No era un fallo del formulario de recibir; ese solo fue donde se notó, porque
allí la columna entera parecía vacía.

Arreglado en una hoja de estilo de todo el sitio, `css/dxpr-checkbox-fix.css`, colgada desde
`tec_production_page_attachments()` al lado del arreglo de la cabecera. Solo toca la casilla **sin
marcar**: la marcada Bootstrap la rellena con el color de acento y le dibuja un visto blanco, y eso
siempre se leyó bien.

Y es la segunda vez en dos días que muerde la misma distinción: **estar en el marcado no es estar en
la pantalla**. La primera fue la pestaña que nadie podía leer. Así que la prueba de punta a punta
ahora exige, además de que la casilla exista, que la hoja que la hace visible esté en la página.

Comprobado: la prueba de punta a punta pasa entera con doce comprobaciones nuevas sobre la lista, el
guardián 61 de 61 con cinco centinelas nuevos, y la prueba de humo sin páginas rotas.


### 2026-08-16 (mañana) — «¿Dónde sale Receive?»: la función estaba, la puerta no se veía

El dueño abrió el pedido `26-1` para probar la recepción recién construida y preguntó lo que no
debería haber tenido que preguntar: **«¿dónde sale Receive?»**. Y tenía razón: en esa pantalla no
salía.

La pestaña existía y funcionaba. Estaba declarada como tarea local sobre la ficha del pedido, junto a
las de View, Edit y Delete que trae ECK, y el bloque que las imprime estaba activo. Lo que pasa es que
**DXPR saca la barra de pestañas del flujo de la página**: posición absoluta, centrada, y subida su
propia altura entera con un `translate(-50%,-100%)` que está en `css/components/tabs.css`. La deja
flotando por encima del borde superior del contenido, que en este sitio es justo donde está la
cabecera pegajosa. En la captura del dueño se ve: ese rectángulo pálido que tapa CUSTOMERS y
SUPPLIERS **es** la barra de pestañas, ilegible.

Y encima hay un segundo motivo, peor que el primero: el bloque tiene una **condición de visibilidad
por rol, solo `administrator`**. Lukpla, coo y manager no habrían visto esa pestaña nunca, ni la de
recibir ni las de ECK, aunque tuviesen permiso para recibir. Es decir, la pestaña no servía de puerta
precisamente para la gente que firma albaranes.

#### El fallo de fondo era la comprobación, no el tema

La prueba de punta a punta daba esto por bueno. Buscaba el texto `/tec_order/776/receive` en el HTML
de la ficha, lo encontraba —está ahí, dentro de la pestaña invisible— y pintaba BIEN. **Presencia en
el marcado no es lo mismo que visible para una persona**, y esa distinción es justo la que tenía que
haber cazado. Una función a la que no hay manera de llegar pasó el examen.

Ahora la prueba cuenta **botones**, no apariciones de la dirección: busca etiquetas de enlace dentro
de `div.btn-default`, que es lo único que alguien puede pulsar.

Ese cambio destapó una trampa de las pruebas que conviene tener anotada. Al exigir que el pedido
cerrado **no** ofrezca recibir, la comprobación falló, y no por un fallo del código: en una petición
de verdad la pestaña desaparece sola, porque el acceso a la ruta la deniega. Lo que ocurre es que
**Drupal calcula las tareas locales de una ruta una sola vez por proceso** —`LocalTaskManager` las
guarda en memoria con el permiso ya resuelto, en `$taskData`— y esta prueba pinta la misma ficha antes
y después de cerrar el pedido dentro del mismo proceso. Vaciar los cachés de render no lo arregla,
porque no es un caché de render. Se comprobó lanzando la misma página en un proceso limpio con el
pedido ya cerrado: ni un enlace.

#### Lo que se ha hecho

- **El botón `Receive` vive donde la gente ya mira**: al lado de `Edit order` y `Print/PDF`, que salen
  de `attachment_6` de la vista de líneas. No depende del CSS del tema y lo ven todos los roles. El
  icono es el visto (`000-ok-16.png`), que de los que hay es el que mejor lee para «dar por bueno lo
  que ha llegado».
- **Los tres botones pasan por un condicional sobre el estado**, el mismo patrón que ya usaban
  `attachment_5` con los pedidos de venta y la lista de proveedores con los de compra: abierto enseña
  editar, recibir e imprimir; cerrado conserva imprimir y pierde los otros dos. Esto arregla de paso
  una incoherencia que venía de antes: **un pedido cerrado seguía ofreciendo editar en su propia
  ficha**, aunque la lista de proveedores ya se lo negaba desde la noche anterior.
- **La pestaña se queda donde está.** No molesta y para un administrador con pantalla ancha sigue
  siendo un atajo. Arreglar el CSS de un tema de contribución para que se lea es otra faena, y no la
  de hoy.
- **El guardián tiene dos centinelas nuevos** sobre la configuración de esa vista: que la rama de
  abierto ofrezca la recepción y que la de cerrado solo imprima. Cualquiera que retoque la vista por
  la pantalla de administración puede llevarse el botón por delante sin notarlo, y eso es exactamente
  lo que acaba de pasar durante seis horas por otro camino.

Comprobado: la prueba de punta a punta pasa entera, el guardián 56 de 56 y la prueba de humo 58
páginas.

### 2026-08-16 — El círculo de la compra se cierra: recibir mercancía, al modo de Odoo

Faltaba la puerta de entrada. Se podía pedir —`/purchase` propone, el botón escribe el pedido, la
cantidad aparece en `Ordered (UoP)` del tablero— y ahí se quedaba todo para siempre. **No había
ningún sitio donde decir que el material había llegado.** El dueño lo vio con el ojo puesto en la
pantalla: *"tiene que haber una manera, ya sea dentro de la purchase order o dentro de `/stock`, que
nos deje indicar que el material ha llegado"*.

Y el daño no era estético. Mientras un pedido contaba como en camino, `/purchase` restaba esa
cantidad antes de sugerir nada, así que **el tablero mandaba comprar cosas que ya estaban en la
estantería** —o peor, dejaba de mandar comprar creyendo que venían de camino unos rollos que llegaron
hace tres semanas y que ya se han gastado.

#### La pregunta que decidió el diseño

El dueño planteó el caso que ningún atajo resuelve: **el proveedor manda 1.980 de los 1.990 y el
resto no va a llegar nunca**. Y dio dos opciones: arreglarlo a fondo *"como lo hace Odoo y SAP,
seguramente ya hayan solucionado este problema"*, o dejar la columna editable a mano *"sin conexiones
que luego pueden desatar un montón de problemas"*. Eligió la primera, entera: *"si hacemos la A, la
hacemos completa. modo Odoo"*.

Los tres principios que comparten Odoo y SAP, y que son los que se han copiado:

1. **Lo pendiente se deduce, nunca se escribe.** Es `pedido − recibido`, calculado cada vez que se
   pregunta. No hay ningún campo con "lo que queda por llegar" ni ninguna casilla donde teclearlo,
   porque una cifra así acaba sin poder cuadrarse contra los pedidos.
2. **Todo movimiento de stock dice de dónde viene.** Cada entrada apunta a la línea de pedido que la
   trajo.
3. **El pedido corto se cierra a propósito.** Los 10 que faltan no desaparecen solos: alguien marca
   que no vienen más, y entonces —y solo entonces— dejan de contar.

#### Lo que se ha construido

- **Cuatro datos nuevos**: lo recibido por línea, la marca de "no viene nada más", su motivo, y la
  referencia del movimiento de inventario a su línea de pedido. Más un **segundo estado** de pedido,
  `closed`, porque hasta esa noche el campo admitía un único valor y por tanto un pedido no podía
  terminar.
- **La pantalla de recibir**, en `/tec_order/{id}/receive`, con pestaña propia en la ficha del pedido
  y atajo desde el número de `Ordered (UoP)` del tablero, que es donde mira quien firma el albarán.
  (Esa pestaña resultó ser invisible; a la mañana siguiente se cambió por un botón. Está contado en la
  ficha de arriba.)
  Se teclea en unidad de compra y **la pantalla enseña, mientras se teclea, en qué se convierte en
  unidad de almacén**. Esa vista previa es lo más importante del formulario: la multiplicación por el
  factor es lo único que puede fallar sin dar la cara, porque mete en el almacén un número creíble y
  equivocado que nadie descubre hasta que se cuentan rollos a mano semanas después.
- El stock se mueve **por la única puerta que ya tenía**, los modelos ECA del gestor de stock, igual
  que el `±` del tablero. No se ha inventado un segundo camino para llegar al mismo sitio.
- **El pedido se cierra solo** cuando ninguna línea deja nada pendiente, y entonces desaparece de
  `Ordered (UoP)` y de los cálculos de `/purchase`.

#### Las tres decisiones del dueño

- **La entrega corta se cierra con la casilla de su fila**, no con un diálogo al final. Odoo pregunta
  una vez, al validar, y solo por las líneas que se quedan cortas: es más elegante y es imposible
  olvidarlo. Se descartó por precio —cuatro horas frente a seis— y se puede añadir encima más
  adelante **sin tocar ni un dato guardado**, porque lo que se escribe es idéntico de las dos formas.
- **Recibir más de lo pedido se acepta, con aviso.** La mercancía está físicamente en el edificio, y
  un stock que miente para que un documento quede cuadrado es peor que una sorpresa.
- **El motivo del cierre es opcional.** Merece la pena escribirlo igual: las líneas de pedido no
  guardan autor ni versiones, así que sin él esa decisión no deja más rastro que una fecha cambiada.

Deshacer una recepción **no se ha construido**, a propósito. Se corrige con el `±` del tablero y
arreglando el recibido a mano en la línea, que está en su formulario justo para eso.

#### Dos cosas que se encontraron por el camino

**Un pedido cerrado se habría quedado sin el botón de imprimir.** En la lista de pedidos del
proveedor, los botones de editar e imprimir viven dentro de un campo condicional que solo se cumple
cuando el estado es `Open`. Mientras hubo un único estado eso no se notaba, porque siempre se
cumplía. Con el estado nuevo, un pedido terminado habría perdido los dos, y perder el de imprimir es
perder la copia para la carpeta de contabilidad. El condicional tiene ahora su rama de "si no": los
cerrados conservan imprimir y pierden editar, que en un pedido terminado es justo lo que no se quiere
que se toque a la ligera.

**Una trampa de Drupal que cuesta media hora.** El control de acceso de la ruta recibía el `768` de
la dirección en crudo en vez del pedido cargado, y reventaba con *"Call to a member function bundle()
on string"*. La causa está en `ArgumentsResolver::getArgument()`: sin declaración de tipo en el
parámetro, Drupal entrega el valor plano; con ella, entrega la entidad. Está anotado en el código
para el próximo que escriba un `_custom_access`.

#### Comprobado

- **La prueba de punta a punta**, `scripts/funciona-la-recepcion.php`, recorre una entrega de verdad
  con sus dos sorpresas: llegan 4 de 10, luego 5 más y se cierra el que falta, y de la otra línea
  llegan 5 de los 4 pedidos. Comprueba la conversión **calculándola aparte** a partir del factor del
  material, que es la única forma de cazar un factor puesto al revés. Deshace todo lo que crea,
  incluidos los niveles de stock que movió el ECA.
- **El guardián** tiene apartado nuevo, el 5, con la comprobación que de verdad importa: **ningún
  pedido abierto sin nada pendiente**. Uno así es exactamente el fallo que se ha arreglado, y solo
  puede reaparecer si el cierre automático se rompe. Pasa 54 de 54.
- **La prueba de humo** pasa 58 páginas, incluida la nueva, que se busca sola: coge el último pedido
  de compra abierto en vez de un número a pelo, que dejaría de comprobar nada el día que ese pedido
  se cierre.
- La prueba de la lista de compra sigue en verde, que era el riesgo real del cambio: `onOrder()`
  ahora devuelve lo pendiente en vez de lo pedido, y las dos pantallas beben de esa función.

### 2026-08-15 (noche) — La ficha del material: tres fallos que el dueño vio en la pantalla y un cuarto que se encontró detrás

El dueño repasó el alta de material con Lukpla delante y sacó cinco pegas. Las cinco eran ciertas y
las cinco están arregladas, pero la última destapó algo bastante peor que lo que se reportaba.

**Lo que se reportó.** Que el campo *Material Type* dejaba escribir libremente en vez de obligar a
elegir; que los dos factores de conversión necesitaban cinco decimales y solo tenían dos; y que
*Safety Stock Qty*, *Reorder Point* y *MOQ* no decían en qué unidad estaban.

**Por qué lo de las etiquetas no era cosmética.** Esa misma tarde el dueño escribió el punto de
pedido y el stock de seguridad de un material en metros, que es como lo compra, cuando esos campos
cuentan unidades de inventario. Como ese material lleva dos metros y medio por unidad de inventario,
la lista de compra le pidió 4.990 metros en vez de 1.990. Nada dio error y la única pista estaba en
un factor de 0,4 escondido en otra pestaña. Ahora las etiquetas llevan `(UoS)` y `(UoP)`, y el
comprobador nuevo encontró un cuarto campo igual de mudo, `Stock level`.

**La creación al vuelo dejó su prueba del delito.** `Material Type` y `Colors` tenían `auto_create`
puesto, y con el widget de select2 eso convierte el desplegable en una caja de texto libre. Se cerró
a las 20:31; el guardián de recuentos, media hora después, delató un tipo de material llamado
**`vcvcvcx`** creado a las 20:18, teclazo puro, con su término, su URL y su ficha. Se le había echado
la culpa al importador de los seis duplicados anteriores, como el color `"Blue "` con un espacio
detrás, y el importador también lo hacía, pero la ficha tenía el mismo agujero.

**Y el cuarto fallo, el que nadie había visto.** Al subir los factores a cinco decimales había que
comprobar que las pantallas los enseñaran, porque el módulo de cálculo de las vistas no lee la base:
lee lo que la columna dibuja. Resultó que en **diecisiete columnas los factores se dibujaban con el
formateador de enteros**. Cuatro pantallas de cálculo de material dividen por ellos con la fórmula
`cantidad / split_into / units`, así que un factor menor que uno —0,4 es normal y corriente— llegaba
a la fórmula como **cero**: una división por cero en la pantalla que dice cuánto material hace falta
para un pedido, sin necesidad de ningún dato raro. Veinte columnas de cinco vistas quedan dibujadas
como decimales con los mismos cinco decimales que guarda el campo.

**Cambiar los decimales de un campo con datos** no se puede desde la pantalla, y la receta quedó en
`scripts/dar-cinco-decimales-a-los-factores.php`: las columnas primero, partiendo de la
especificación que Drupal ya tenía apuntada para no perder ninguna propiedad; luego el apunte de
esquema, que si se queda diciendo dos, el día que alguien guarde el campo Drupal "arregla" la columna
hacia atrás; luego la configuración, escrita en crudo porque guardarla como entidad dispara la
comprobación que prohíbe el cambio; y la copia de la definición instalada, que al contrario de lo
que suponía la nota del cambio anterior, para estos dos campos sí existía.

**Los recuentos de contenido dejan de dar veredicto.** `comprobacion.php` medía el contenido contra
dos caras, "con juego de pruebas" y "vacío", y ese día apareció una tercera: el dueño usando el ERP.
Sus dos materiales y su pedido de compra pusieron cuatro guardianes en rojo sin que nada estuviera
roto. Un guardián que da la alarma cada vez que alguien hace su trabajo se acaba mirando de reojo, y
entonces no avisa el día que hay que avisar. Las siete cifras se siguen imprimiendo, marcadas como
dato y al lado de lo que trae el juego de pruebas, pero el aprobado lo dan ahora las 49
comprobaciones que no dependen de cuánto contenido haya. Los vocabularios de referencia —colores,
tipos de material, tallas, unidades— siguen juzgando, y bien que hacen: fue uno de ellos el que cazó
el `vcvcvcx`.

### 2026-08-15 — `tec_gui`, fuera del ERP: el producto del programador anterior llevaba dos años atornillado a las líneas de pedido

El dueño preguntó qué era `tec_gui`, porque el nombre no dice nada, y decidió retirarlo entero.

**Qué era.** No la interfaz gráfica, como sugiere el nombre: era el **producto original**, el que
había antes de `tec_product`. Lo delatan sus propias tablas, que se leyeron una por una antes de
tirarlas: `field_product_name`, `field_tec_brand`, `field_tec_customer`, `field_tec_images`,
`field_tec_pattern`, `field_tec_product_type`, `field_tec_sales_price`, `field_tec_size`,
`field_tec_bom`, `field_tec_view_bill_of_materials`. Eso es un producto, campo por campo. Cuando
llegó el producto de verdad, alguien añadió `field_tec_product` a las líneas de pedido y le puso al
viejo la etiqueta **`Product - DEPRECATING`** a mano. Ahí se quedó la faena, dos años.

**Por qué iba primero de la lista.** No por los dos errores en rojo del informe de estado, que eran
el síntoma cómodo. Por esto: `field_tec_gui_product` era un campo **obligatorio** de las líneas de
pedido de venta, la pieza más usada del ERP. Retirarlo con dos líneas en la base cuesta lo que
cuesta un `truncate`; retirarlo con los 857 materiales importados y pedidos reales dentro es otra
conversación. Y había un segundo abaratamiento que conviene no olvidar para el resto de tareas
estructurales: **la base del servidor se va a reemplazar con la de local**, así que no hizo falta
escribir ningún gancho de actualización. Se borró aquí y viaja ya limpio.

**Lo que se encontró al levantar la alfombra**, y que no coincidía con lo apuntado:

- El tipo de entidad **no tenía ni un subtipo**. Cero. No se podía crear una ficha GUI ni queriendo:
  un armario sin estantes, con la puerta atornillada a la pared.
- Las tablas eran **trece**, no doce, y las trece vacías.
- La tabla del campo tenía **22 filas**, no 20. Y el campo, además, **no estaba instalado**: existía
  en la configuración pero no en el registro de "qué hay en la base". Ese detalle decidió el orden de
  la operación, ver abajo.
- De las 22 filas, 21 eran huérfanas puras —ni línea ni ficha GUI—, y **la 22 colgaba de la línea de
  prueba viva**. La escribió `scripts/fabricar-el-juego-de-pruebas.php`, que rellenaba
  `field_tec_gui_product` con el id del producto para poder satisfacer un campo obligatorio muerto.
  O sea que la mina que se describía en teoría ya había explotado una vez, en nuestro propio guion.
  Esa línea del guion se ha quitado.
- **Ningún proceso de ECA lo tocaba**, ni ningún módulo. Se buscó en los treinta y dos procesos y en
  los cuatro módulos propios. Eso lo hizo mucho más barato que lo de Marcas y Patrones, donde el
  problema eran justamente los dos procesos de duplicar producto.
- Había **una marca huérfana** puesta con la bandera `tec_eca_gui_lock`, sobre una ficha GUI que no
  existía. Resulta que era la excepción que la comprobación del ERP daba por buena desde el principio
  —*"en reposo solo debe haber una bandera puesta, la del panel de ECA"*—. No era el panel de nada:
  era basura. Ahora la comprobación exige **cero banderas puestas**, que es lo que significaba.

**El orden, que no era negociable.** Las banderas y el modo de vista antes que el tipo de entidad,
porque lo declaran como su entidad y se quedarían apuntando al vacío. La tabla del campo **vaciada
antes** de borrar el campo, y esto es lo fino: el núcleo, cuando borras un campo con datos dentro,
renombra su tabla y la deja en la cola de purga; vacía, la tira en el acto. Vaciarla primero cambió
una operación con cola pendiente por una limpia. Y el tipo de entidad al final, porque **ECK, al
borrarlo, busca él solo todos los campos de referencia que apuntan a él, los borra y desinstala el
tipo**. Eso se leyó en `EckEntityType::preDelete()` antes de ejecutar nada, no se supuso.

Lo que ECK **no** se lleva son las tablas `tec_gui__field_*`: sus definiciones de campo se habían
borrado de la configuración hace años y las tablas sobrevivieron, así que no hay nada que las
reclame. Esas once se tiraron a mano en el último paso, después de comprobar una por una que estaban
vacías.

**La cuenta final**: 1 tipo de entidad, 13 tablas, 2 campos con sus almacenes, 2 banderas, 4
acciones, 1 selector etiquetado `TEMP` y sin un solo widget dentro, 1 modo de vista, 29 permisos en
tres roles, 1 marca huérfana, 1 línea del service worker y 6 entradas del botón de cancelar. En
configuración: 13 objetos borrados y 13 actualizados.

**Cómo quedó comprobado.** El informe de estado pasa de dos errores a **uno**, y el que queda es el
HTTPS de local, que es normal aquí. `que-esta-descuadrado.php` dice *"no hay nada descuadrado"*. La
comprobación del ERP pasa **50 de 50** —con dos guardianes nuevos, ver abajo— y la prueba de humo
carga **53 páginas de 53**, incluidas la línea de pedido de venta que tenía el campo, los dos pedidos
y el borrador de compra. Y lo que de verdad importaba: la comprobación crea una línea de pedido de
venta por código y ECA le recalcula el total, o sea que **crear líneas sin el campo obligatorio
funciona**.

**Dos guardianes nuevos**, porque volver a entrar es fácil:

- *ninguna definición de entidad descuadrada*. Es el general, y es el que mantiene limpio el informe
  de estado. Un rojo que siempre está enseña a no mirar los rojos.
- *no queda nada de `tec_gui`*. Basta importar configuración vieja para que el tipo de entidad
  reaparezca, y con él su campo obligatorio. Mira las cuatro cosas que importan —el tipo, las tablas,
  los campos con ese destino y los permisos— y **no** busca el texto "tec_gui" a lo bruto, por una
  trampa que casi se lleva algo vivo: la vista **`tec_gui_bom_item_viewfields`** lleva "gui" en el
  nombre y no es de esto, la usan dos campos vivos de los despieces. Está escrito en el propio
  guardián para que nadie lo repita.

**Dos hallazgos de rebote**, apuntados en Deuda técnica: entre las trece tablas había una
`tec_gui__feeds_item`, que apunta a que las dos importaciones huérfanas de Feeds importaban a `tec_gui`
y no a `tec_product` —si se confirma, no hay nada que recuperar—; y al guardar los roles, Drupal
retiró permisos de un **`tec_app_link`** que ya no existe, otro tipo de entidad abandonado, este ya
sin tablas y sin dejar rojos.

**Una consecuencia en Patrones**, contada entera en su ficha de decisión: Patrones tenía un campo que
apuntaba a `tec_gui`, se fue con la retirada, y la vista `tec_pattern_elements` se quedó sin nada que
mostrar, así que Drupal la desactivó. Su bloque sigue incrustado en la ficha de un término de patrón.
Hoy no molesta porque hay cero términos; si Patrones vuelve, se arregla antes.

Guion: `scripts/quitar-a-gui-del-erp.php`, con ensayo por defecto y cinco guardias que lo abortan si
algo no está como se comprobó. Volcado previo en
`backups/actatec_antes_de_quitar_gui_2026-08-15.sql.gz`.

### 2026-08-15 — Los cinco enlaces a los catálogos llevaban perdidos en todas las pantallas de editar

Empezó con una pregunta pequeña del dueño: *"necesito un link de acceso rápido a la URL donde se
almacenan las sizes, igual que hicimos con los product type"*. La respuesta corta es que **ese enlace
ya estaba escrito en el código** desde hacía meses, para producto, color y talla. No se veía.

**El primer motivo, y es de los que duran años sin que nadie lo note.** Los enlaces se enganchaban
comparando el identificador del formulario contra `tec_product_tec_product_form`. Pero la ruta de
editar usa la operación `edit`, y Drupal le pega el nombre de la operación al identificador
(`EntityForm::getFormId()`), así que allí el nombre real es `tec_product_tec_product_edit_form` y la
comparación no acertaba nunca. O sea que **los enlaces solo salían al crear una ficha, jamás al
editarla**. Medido antes de tocar: al añadir un producto salía *Manage product types*; al editar ese
mismo producto no salía ninguno de los cinco.

Y lo que hacía el fallo invisible: **el del material sí funcionaba**, que era justo el que el dueño
tenía delante cuando dijo *"igual que hicimos con los product type"*. Funcionaba por casualidad,
porque los términos de taxonomía usan la operación `default` para añadir y para editar, así que su
identificador no cambia. Un fallo que deja funcionando el caso que más se usa es un fallo que no se
denuncia solo.

**El segundo motivo, más escondido, y sin él el primero no bastaba.** El color y la talla casi nunca
se editan en su página: se trabajan **embebidos** dentro del formulario de producto con
`inline_entity_form`, y un formulario embebido **no pasa por `hook_form_alter()` con su propio
identificador** — solo salta el del padre. Con lo cual el enlace de tallas no podía aparecer donde de
verdad hace falta ni arreglando los identificadores. Hacía falta
`hook_inline_entity_form_entity_form_alter()`, que es por donde `EntityInlineForm` deja tocar el
subformulario ya construido.

**El arreglo va por la entidad, no por el nombre del formulario.** Se lee la ficha que se está
editando y se decide por su tipo y su grupo, que es inmune a la operación y no se desfasa el día que
alguien añada una operación nueva. La tabla de qué enlace va con qué campo queda en un solo sitio y la
usan los dos ganchos. Como ahora dos caminos pueden llegar al mismo campo, el ayudante mira si el
enlace ya está antes de añadirlo: sin eso saldría dos veces.

**Comprobado por el camino real, que aquí es la única forma.** Los subformularios de color y talla se
abren por AJAX al pulsar *Add new size* o *Edit*, así que pedir la página nunca los ve — de hecho la
primera versión de la comprobación daba un falso fallo por esperar el enlace del color en una carga
normal del formulario de producto, donde solo hay tabla y botón. Así que la comprobación monta el
elemento de `inline_entity_form` y deja que el módulo lo construya, que es el mismo camino que sigue
al pulsar el botón. Cubre seis pantallas y los tres casos embebidos, incluido **añadir una talla
nueva**, que es exactamente el momento en el que alguien echa en falta una talla del catálogo. Queda
en `scripts/salen-los-enlaces-de-catalogo.php`. Comprobación del ERP 48/48 y 53 pantallas sin
problemas.

**De camino, por qué la talla de prueba tiene ese nombre tan raro, y esto interesa para el
importador.** El dueño vio *"PRUEBA producto - Black - XXS"* en la columna Sizes y no encontraba ese
texto en el vocabulario de tallas. No está: el vocabulario tiene solo las tallas de verdad, y ese
texto es el **título de una *Size variation***, que es la ficha donde vive el BoM. Lo escriben
dos procesos de ECA —`process_qlf96jn` al insertar y `process_ocmwa9c` al actualizar— y su receta es
solo el nombre de la talla: *"XXS"*. Pero el de insertar lleva la condición **"solo si el título llega
vacío"**, y el de actualizar solo lo reescribe **si cambia la talla**. Al fabricar el juego de pruebas
se le escribió un título a mano, así que ECA lo respetó y ahí se quedó; la minúscula de *"producto"*
es la prueba de que es texto congelado y no una fórmula, porque no siguió al producto cuando se
renombró. **La consecuencia para el importador de productos es directa**: si el importador escribe un
título en las variaciones de talla, ECA no lo va a corregir nunca. Hay que dejar ese campo vacío y que
lo ponga el ERP.

### 2026-08-15 — Marcas vuelve al ERP: no había que retirarla, había que devolverle la puerta

El dueño leyó el punto pendiente *"Retirar Marcas y Patrones"* y respondió lo que hacía falta:
*"pensaba que lo que habías borrado era la información dentro de esos dos módulos, no los módulos en
sí"*. **Y el punto estaba mal planteado**, esta vez del todo: la marca es obligatoria para crear un
producto, así que retirarla habría dejado el catálogo sin poder nacer. Corregido arriba.

**Nunca se perdió la función; se perdió la puerta.** El vocabulario, el campo en productos, la vista
`tec_brands`, los formularios y los permisos en tres roles estaban intactos. Lo que faltaba era el
**nodo 4**, la portada que embebe el bloque de la vista y que pone el icono en el inicio. Y no lo
borró ninguna de las limpiezas de estos días: cuando la prueba de humo empezó a pedir portadas ya
contaba **doce** —1, 2, 3, 5, 6, 7, 9, 10, 11, 12, 13, 14—, o sea que el 4 llevaba caído desde antes.
Nadie lo notó porque hasta ahora nadie necesitó una marca.

**Dos fallos que se estaban tapando el uno al otro, y esto es lo que hay que llevarse.** El botón
`+ Brand` de la vista tenía `empty: false`, o sea puesto para **esconderse justo cuando la lista está
vacía**: el mismo fallo que dejó al CRM sin manera de crear un cliente. La auditoría del 14 de agosto
encontró diez botones así **recorriendo las portadas**, y este se le escapó por una razón incómoda:
su portada no existía, así que no había por dónde llegar a la vista para verlo. Un fallo escondía al
otro. Si solo se hubiera devuelto la portada, la pantalla habría salido en blanco y sin manera de
crear la primera marca; si solo se hubiera arreglado el botón, nadie lo habría visto nunca.

**Rehecha con el número 4 a propósito.** La vista nombra `/node/4` en cuatro enlaces —el de editar
cada marca y el destino del botón de crear—, igual que productos nombra `/node/1` y colores
`/node/9`. Recuperando el número, los cuatro enlaces vuelven a valer sin tocar ni una ruta. El molde
es la portada de **colores**, que es el caso gemelo —un vocabulario con una vista en cuadrícula dentro
de un nodo—, y se copian sus tres piezas con sus pesos, así que esta queda indistinguible de las que
ya funcionaban.

**El icono estaba en el disco.** `isometric-brand-100.png` es una de las cinco imágenes de marca que
se salvaron del borrado de fotos del 14 de agosto. Seguía en la carpeta pública de 2024-03, pero su
ficha había muerto con el nodo, así que se copió al sistema **privado**, que es donde guardan los
otros diez iconos, y se fichó de nuevo. Se le da orden 8, detrás de colores, que es el otro catálogo.

**Comprobado lo que fallaba, no solo el código HTTP.** Un 200 ya nos engañó una vez. Así que
`scripts/funciona-la-pantalla-de-marcas.php` mira el contenido: que el icono salga en `/start` y
apunte a `/b`, que la pantalla traiga el botón y la marca, y **sobre todo que el botón siga saliendo
con la lista vacía**, que es el caso que fallaba — para probarlo despublica un momento la única marca
y la vuelve a publicar al terminar, pase lo que pase. También se comprobó que el enlace del botón
responde con la barra de más que lleva escrita (`/add/?destination=`) y sin ella: las dos dan 200. Y
el final de la cadena: el desplegable de marca del formulario de producto es una **lista fija y
obligatoria**, y ya ofrece marca, o sea que el producto se puede guardar. Con cero marcas no se podía,
y por eso esto no era cosmético.

Prueba de humo en verde, **53 pantallas** con `/b` dentro, que la recoge sola porque desde el 14 de
agosto pide todas las portadas.

**Y aprovechando el hilo, cinco botones escondidos más, que el barrido de portadas no podía ver.**
Si el de marcas se escapó porque su pantalla no existía, había que preguntarse a qué más no llegaba
ese método. Se buscó otra vez, pero **por configuración en lugar de por pantallas**: la configuración
está ahí aunque no haya puerta por donde llegar. Aparecieron cinco, en displays de los que no cuelga
ninguna portada. Cuatro arreglados:

- **`tec_inventory_transactions` / `block_1`**, y este era un fallo vivo: es el historial de stock de
  un material, y un material recién creado no tiene movimientos por definición, así que el botón
  `+/- Stock` se escondía **siempre el primer día**. No llegó a bloquear a nadie porque el listado de
  inventario tiene el suyo por fila, y ese sí estaba bien puesto.
- **`tec_products` / `block_2` y `block_4`**, dos listados de productos. Con el catálogo por importar,
  la lista vacía es el estado normal de las próximas semanas.
- **`tec_colors` / `page_1`**. El bloque de la portada `/cl` ya estaba bien, y por eso el barrido de
  portadas lo dio por bueno: mira displays, y el fallo estaba en el otro.

El quinto, **`tec_patterns` / `block_1`, se deja a propósito** por orden del dueño: Patrones está
pendiente de decidir si se retira, y no tiene sentido arreglarle un botón a una pantalla que puede
desaparecer.

**Guardián nuevo, el 48**: *"ningún botón de crear se esconde con la lista vacía"*. Recorre las áreas
de cabecera y pie de todas las vistas, se queda con las que llevan un enlace de crear, y exige que
ninguna tenga `empty: false` salvo la de patrones, que figura en una lista de excepciones con su
motivo escrito. Mira configuración, no pantallas, que es la lección de las dos veces anteriores. El
diagnóstico suelto queda en `scripts/queda-algun-boton-escondido.php`. Comprobación 48/48.

**Corrección del mismo día: la portada volvió, pero torcida.** El dueño la abrió y avisó: *"funciona,
pero ha perdido el formato visual"*. Tenía razón — la rejilla con la marca se pintaba **encima** del
título, y *"Brands"* aparecía debajo de las tarjetas. La portada respondía 200, tenía todas sus piezas
y estaba mal.

**La culpa es de `appendComponent()`, y esta trampa merece quedar escrita** porque la receta de copiar
una portada es lo único que se va a reutilizar el día que se haga la siguiente. El guion copiaba las
tres piezas del molde de colores con sus pesos —que son los que mandan el orden en pantalla— y las
metía en la sección con `Section::appendComponent()`. Lo que no se miró es la primera línea de ese
método en el núcleo:

```php
public function appendComponent(SectionComponent $component) {
  $component->setWeight($this->getNextHighestWeight($component->getRegion()));
```

**Reescribe el peso antes de guardar la pieza.** O sea que copiar los pesos del molde no sirve
absolutamente de nada si luego se añaden con ese método: cada pieza se queda con el número que le toca
por orden de llegada. En colores los pesos son título 2, cuerpo 3 y rejilla 4; en marcas quedaron
cuerpo 0, rejilla 1 y título 2.

**Y hay un segundo detalle que es el que convirtió un fallo invisible en un fallo visible**, al revés
de lo que suele pasar: `getComponents()` devuelve las piezas en el orden en que están **guardadas**,
que no es el orden en que se pintan. El bucle las recorrió cuerpo, rejilla y título, y por eso el
título acabó último. Si el molde las hubiera tenido guardadas en el mismo orden en que se pintan, la
copia habría salido bien **por casualidad**, el fallo se habría quedado dormido en la receta y lo
habría pagado la próxima portada. Salió mal el primer día, que es la mejor manera de que salga mal.

**Arreglado sobre el nodo que ya existía, sin recrearlo**, porque el número 4 es justo lo que había
que conservar. Los pesos no se escriben a mano en ningún sitio: se leen del nodo 9 emparejando pieza
con pieza, así que el día que se retoque la portada de colores el guion sigue diciendo la verdad en
vez de repetir tres números caducados. Queda como **revisión 16**, con su motivo escrito, de modo que
la versión torcida sigue en el historial. `scripts/poner-en-orden-la-portada-de-marcas.php`.

La receta, `scripts/devolver-la-pantalla-de-marcas.php`, ya no puede repetirlo: le pasa las piezas al
**constructor de `Section`**, que es el único camino que respeta el peso porque llama a
`setComponent()` sin tocarlo, y de paso copia los ajustes de terceros de la sección, que antes se
perdían —hoy da igual porque colores no tiene ninguno, pero no daría igual con otro molde—. Como ese
guion se para en seco si el nodo 4 existe, no se puede probar sin borrar el nodo, así que la receta se
prueba aparte: `scripts/respeta-el-peso-al-copiar-la-seccion.php` monta la misma sección por los dos
caminos y los enseña juntos. Con `appendComponent()` salen los pesos 0, 1 y 2 — **exactamente los que
tenía el nodo roto**, o sea que reproduce el fallo, no solo lo describe. Con el constructor salen 2, 3
y 4, los del molde.

**La zona gris y el botón no eran fallos, y esto se midió antes de tocar nada.** El dueño señaló las
dos cosas en la captura y las dos salen igual en `/cl`:

- **La zona gris de la mitad inferior es el fondo del tema.** No hay ningún bloque de más: `/b` y
  `/cl` tienen el **mismo armazón**, 44 envoltorios cada una en los tres primeros niveles. Lo que
  cambia es cuánto contenido hay encima — `/b` pinta **2** celdas de rejilla y `/cl` **64**. Con una
  marca el contenido no llega a la mitad de la pantalla y el resto lo pinta el fondo; en colores no se
  ve porque las 32 fichas la llenan. El día que haya marcas de verdad desaparece sola.
- **El botón `+ Brand` sale con el mismo HTML y el mismo CSS que el de colores**: los dos son
  `div.text-align-right > div.btn-default > a`, y las dos pantallas cargan las mismas hojas de estilo
  salvo tres que solo necesita `/cl` —el filtro de búsqueda expuesto, la muestra de color y el buscador
  del tema—, ninguna de las cuales define `btn-default`. Se miró también si el tema trata distinto al
  primer bloque de una región, que habría explicado el botón sin tocar el botón: **no**, sus reglas de
  `:first-child` solo mueven el `h2` de los bloques de las barras laterales. Se veía raro porque
  estaba **en lo alto de la página, sin el `h1` encima**. Con el orden arreglado se coloca donde el de
  colores.

**El guardián que faltaba.** `scripts/funciona-la-pantalla-de-marcas.php` dio **"todo bien" con la
pantalla visiblemente torcida**, y eso es lo más incómodo de todo esto: comprobaba que el título y el
botón estuvieran en el HTML, no **dónde**. Es la tercera versión del mismo error —primero creímos que
un 200 bastaba, luego que estar bastaba— y ahora exige además que el título vaya antes de la rejilla.
Lo encontró el dueño mirando la pantalla, que es justo lo que estas pruebas tendrían que ahorrarle.

La comparación completa, pieza a pieza y HTML contra HTML, queda en
`scripts/donde-se-desordeno-la-portada-de-marcas.php`. El nodo es contenido y no configuración, así
que no hubo nada que exportar. Comprobación **48/48**, marcas **todo bien**, **53 pantallas** y **0**
con problemas.

**De propina, una cifra que no cuadraba y no era un fallo.** La comprobación empezó a dar 47/48 por
las tallas: 15 donde esperaba 14. No lo había roto el arreglo. La primera sospecha —que fuera basura
que la propia comprobación se dejara detrás, porque su apartado 3 crea una variación de talla— era
razonable y era **falsa**: la de más se llama **"20 oz"** y continúa la serie de onzas que ya estaba,
y el registro dice *"Created new term 20 oz"* desde el formulario del vocabulario con el usuario 1. La
metió el dueño a mano mientras se arreglaba la portada. **Borrarla para que la cuenta cuadrara habría
sido tirar un dato de verdad**, así que se subió la referencia a 15 con el motivo escrito. Que una
cifra de referencia falle no significa que algo esté roto: significa que hay que mirar quién la movió.
`scripts/quien-creo-la-talla-de-mas.php`.

### 2026-08-14 — Los 104 CSV y Excel de las importaciones de ensayo, fuera, y dos importadores huérfanos al descubierto

Decisión del dueño: los listados de materias primas y los BoM que se subieron entre marzo de
2024 y julio de 2025 eran todos ensayos y se borran. Fuera **104 fichas** de hoja de cálculo, 1,9 MB,
más **10 archivos sueltos** en `private://feeds` que ya no tenían ficha en la base y que nadie se
había llevado. Quedan **69 ficheros** en todo el ERP: 39 fuentes tipográficas, 28 imágenes y 2 hojas
de estilo. La carpeta privada, que esta misma noche empezó en 61,6 MB, se queda en **5,1 MB**.

**El script se paró solo la primera vez, y por eso mereció la pena escribirlo así.** Comprueba dos
cosas antes de borrar y no borra si alguna no sale limpia. La primera fue una falsa alarma mía: cuatro
ficheros tenían usos registrados a nombre de un dueño de tipo `fetcher` con un UUID, y yo esperaba
`feeds_feed`. Es correcto y está en `UploadFetcher::deleteFile()`: Feeds no apunta el uso a nombre del
importador, sino del tipo de complemento que lee el fichero, y el identificador es el UUID del
importador. La segunda no era falsa alarma, era simplemente inofensiva: dos ficheros aparecían
escritos dentro de la tabla `batch`, en **lotes de importación abandonados en abril y mayo de 2024**
que llevan dos años ahí porque el cron nunca ha corrido. No eran enlaces de descarga, era el estado
serializado de importaciones que nunca terminaron. `system_cron` se los llevará el día que el cron
arranque.

**El hallazgo de verdad: dos de los tres importadores están huérfanos.** Al ir a quitarles el origen
para que no apuntaran a un fichero que ya no existe, saltó una excepción: las fichas 5 y 6, "Primo
products" y "Trust products", son de tipo `tec_products_csv_importer`, **un importador de productos
cuya configuración ya no existe**. Confirma lo que el dueño recordaba, que se intentó importar
productos, y que se quedó a medias. La lista de `/admin/content/feed` carga bien, pero `/feed/5/edit`
revienta. Está apuntado con su arreglo en la sección del importador; no se ha tocado porque borrar
fichas que nadie pidió no es limpiar, es decidir por otro.

**Y de paso quedó diagnosticado el importador**, que llevaba en el backlog como "hay que
diagnosticarlo". Los ficheros sirvieron para eso antes de irse: se leyeron por dentro, se comparó lo
que traen con lo que la configuración espera, y salieron cuatro cosas —tres campos conectados de
quince, una columna que se llama distinto en el fichero y en el importador, el proveedor que viene
vacío en el Excel y obligatorio en la especificación, y un importador de BoM que no conecta
ni el material ni la cantidad—. Todo eso está escrito arriba, en la sección del importador.

Antes de esto había una copia completa de las dos carpetas de ficheros, hecha una hora antes para el
borrado de las fotos, así que los 104 están en
`C:\laragon\backups\ficheros-antes-del-borrado-20260814\private\feeds`. Si algún día hace falta ver
qué formato exacto tragaba el importador, están ahí: el listado de materias son 857 filas y dieciocho
columnas.

Después: comprobación **47/47** con el recuento de ficheros en 69, prueba de humo 30 páginas y 0
problemas.

### 2026-08-14 — Las 142 fotos sin dueño, fuera, y las cinco imágenes de marca que casi se van con ellas

Al vaciar los datos de prueba se fueron los productos, las marcas, los patrones y los 869
materiales, pero no sus fotos: Drupal solo borra un fichero cuando se lo mandas. Quedaron 315 fichas
de fichero, casi todas huérfanas de golpe. Se han borrado **142 fotos**, y con ellas **304 versiones
recortadas** que Drupal purga solo al borrar el original: **446 archivos y 57,2 MB** de disco. La
carpeta privada baja de 61,6 a 7,1 MB y la pública de 25,8 a 23,1. Quedan 173 fichas.

**Lo que casi sale mal, y es el motivo de que esto lleve un reconocimiento delante.** La regla obvia
—"borra todo lo que no use nadie"— se lleva por delante el logotipo del ERP, el logotipo del panel
de administración, los dos favicon y la imagen de fondo de la pantalla de entrar. Son cinco, no dos:
están en `dxpr_theme.settings`, `gin.settings` y `gin_login.settings`. El contador de usos de Drupal
solo cuenta lo que apunta desde el campo de una ficha; **lo que apunta desde la configuración no lo
cuenta**, así que para el contador esas cinco no las usa nadie. Gin es el tema de administración, o
sea que ahí se iba la marca de todas las pantallas internas de golpe.

**Y hay un segundo hallazgo, que además desactiva un miedo que apunté ese mismo día.** Esas cinco
imágenes **no tienen ficha en la base de datos**: el formulario de ajustes del tema las dejó en la
raíz de la carpeta pública sin registrarlas en `file_managed`. Consecuencias buenas: ningún borrado
de fichas puede tocarlas, y `file_cron` tampoco, porque solo borra ficheros gestionados. Yo había
avisado de que si estuvieran marcadas como "temporales" el cron se las llevaría a las seis horas de
encenderlo; no es el caso, y no por suerte, sino porque no son suyas. Consecuencia mala: **nadie las
vigila**. Si alguien borra el archivo, la configuración sigue apuntando ahí y no salta ningún error;
simplemente desaparece el logotipo. Por eso el guardián tiene ahora una comprobación nueva que las
lee de la configuración —no de una lista escrita a mano, para que siga valiendo el día que se cambie
el logotipo— y verifica que el archivo sigue en el disco. Comprobación **47/47**, y las cinco piden
un 200 por HTTP con su tamaño correcto.

**El reparto de los 315.** Se quedan 32 que tienen usos, entre ellos los once iconos de la portada y
los iconos de la aplicación móvil. Se quedan **141 que no son fotos**: las fuentes del tema, dos
hojas de estilo y un montón de CSV y Excel de las importaciones de BoM de 2024. Esos CSV se
fueron una hora después, en la entrada de arriba. Y se fueron las 142 fotos.

**Las siete fotografías que no eran basura.** Entre las candidatas había fotografía de producto de
verdad, en alta resolución: los guantes **ACTA Thai Evolution** en azul, rojo, negro y blanco, de
2,5 a 4,7 MB cada una, más un Trust Squire, dos Joya y el fichero de logotipos de Trust. Eso son 32
de los 52 MB, y es trabajo que no rehace ningún importador —de hecho el importador de materiales ni
siquiera sabe meter el campo de imagen—. Están en la copia de seguridad de la carpeta entera, pero
enterradas entre miles de archivos y con fecha en la ruta, así que antes de borrar se copiaron
**doce fotografías, 32,5 MB**, a `C:\laragon\backups\fotos-de-producto-anv`. El criterio: lo que
lleva el nombre de la casa o de una marca de cliente, y toda foto de más de un mega, que a ese
tamaño ya es una cámara y no una captura de pantalla.

**La red de seguridad.** Antes de tocar nada, copia completa de las dos carpetas de ficheros a
`C:\laragon\backups\ficheros-antes-del-borrado-20260814`: 3.523 archivos, 87 MB, ninguno fallido. El
borrado es reversible desde ahí.

**Cómo se decidió qué sobraba.** `scripts/mirar-los-ficheros.php` reparte sin tocar nada, y
`scripts/borrar-las-fotos-huerfanas.php` vuelve a calcular el reparto en vez de traerse una lista de
identificadores: si algo cambia entre mirar y borrar, cambia el reparto y no se borra por una lista
vieja. La comprobación cruzada buscó el nombre de cada candidata en **392 columnas de texto de la
base, 23,5 millones de caracteres**, por si alguna estaba puesta a mano dentro de un texto o de una
sección de diseño, donde el contador de usos no llega. Salió una sola coincidencia, y era el propio
nombre dentro de la tabla de configuración: el fichero 102 se llama `acta-octa-black-white.jpg`
igual que el logotipo, pero vive en `public://2024-03/` y no en la raíz, o sea que es una copia
suelta y no el logotipo.

Después: comprobación 47/47, prueba de humo 30 páginas y 0 problemas, `config:status` sin
diferencias, los once iconos del inicio verificados en el disco con sus recortes, y la pantalla de
entrar cargando.

### 2026-08-14 — El CSRF del servidor, cerrado desde el navegador y sin desplegar

El servidor llevaba desde mayo con **ECA 1.1.7 y `eca_ui` encendida**, o sea con las rutas que
`SA-CONTRIB-2025-031` necesita: un enlace preparado que, abierto por alguien con sesión de
administrador, apagaba o borraba un automatismo sin más confirmación. Es el único punto de todo este
backlog que empeoraba solo con el paso del tiempo, y esperaba al despliegue completo, que es media
jornada. Ya no: se ha cerrado desinstalando el módulo, en tres rondas de clics, y **la versión
vulnerable sigue instalada pero sin puerta por la que entrar**.

Comprobado desde fuera, antes y después, sin necesidad de entrar: `/admin/config/workflow/eca`
respondía **307** a la página de acceso —la ruta existía y Drupal mandaba a identificarse— y ahora
responde **404**, igual que `/admin/config/workflow/eca/settings`. Y `/user/login` sigue dando 200,
o sea que el sitio está entero.

**Lo que costó, y que era una idea equivocada mía.** Dije que bastaba marcar ECA UI y aceptar cuando
Drupal pidiera llevarse también el modelador. Al revés: Drupal **bloquea la casilla** mientras alguien
dependa del módulo, y no ofrece arrastrar a nadie. La cadena era de tres, y hay que deshacerla de
abajo arriba, una ronda por módulo: **BPMN.iO for ECA** → **ECA BPMN** (`eca_modeller_bpmn`) → **ECA
UI**. Cada ronda libera la casilla de la siguiente. Vale la pena recordarlo porque es al contrario de
lo que hace la lista de módulos al *activar*, donde sí arrastra las dependencias sin preguntar.

**Que no se pierde nada estaba verificado antes de proponerlo**, y se cumplió: los 36 procesos
dependen de `eca_content` y `eca_log`, y los 36 diagramas del módulo base `eca`, no del modelador,
así que ni los automatismos dejaron de correr ni los dibujos desaparecieron. Lo único que se ha ido es
la pantalla para editarlos, que en producción no usa nadie.

**Por qué lo tuvo que hacer el dueño y no yo**, que es la parte reutilizable: por SSH se entra bien
con la llave, pero **`drush` allí no llega a la base de datos**. `settings.php` es `root:www-data`
con permisos `640` y el usuario `david` no está en el grupo `www-data`, así que no puede leer las
credenciales. `drush status` sí contesta la versión, porque eso lo saca del código y engaña; cualquier
comando que toque datos responde *"Drush was unable to query the database"*. Es la misma pared del 13
de agosto con `rebuild_access`, y la que hace falta la contraseña de `sudo` para saltar. Mientras no
esté, **todo lo que toque datos en el servidor se hace por el navegador**, no por SSH.

### 2026-08-14 — Fuera lo que no usaba nadie: un tipo de entidad, siete vistas, siete campos, catorce tablas y una llave maestra

La auditoría de la mañana señaló nueve candidatos a desaparecer. Se han ido todos, pero lo que
merece quedar escrito no es la lista, es **cómo se pasó de "parece muerto" a "es muerto"**, porque la
primera pasada de comprobación estuvo a punto de salvar cosas que no hacía falta salvar y de tirar
cosas que sí.

**La primera búsqueda mintió, y en las dos direcciones.** Buscaba el identificador de cada vista como
trozo de texto dentro de la configuración. Resultado: `tec_order_material_calculation` parecía
tener trece dependencias, y `dev` cuatro. Ninguna era real. Las de la primera eran los nombres de
otras tres vistas que **empiezan igual** (`..._summary`, `..._terms`), y las de la segunda eran
palabras que llevan "dev" dentro. La segunda pasada buscó distinto: Drupal guarda la configuración
serializada, y una cadena serializada lleva su longitud delante, `s:14:"identificador"`. Buscando con
la longitud exacta solo se encuentra el valor completo, nunca un trozo. **La diferencia entre las dos
pasadas es la diferencia entre "aparece" y "es"**, y con la primera no se puede decidir nada.

Con la búsqueda buena quedó una sola duda de verdad: siete campos `viewfield` nombraban la vista del
*Excel Lover*. Se abrió uno y era una **lista blanca de casillas** —el editor puede elegir entre
estas vistas— con esa casilla **en cero**. Lo que el campo pinta de verdad se guarda aparte, por UUID,
y ninguno de los siete UUID de las vistas condenadas aparecía en ningún campo. De paso se limpiaron
esas siete casillas fantasma: si se dejan, el próximo repaso vuelve a encontrarlas y hay que volver a
investigar lo mismo.

Lo que se ha ido:

- **`tec_app_link`**, un tipo de entidad con seis subtipos para enlaces a redes sociales (Facebook,
  Instagram, LINE, WhatsApp, X y teléfono). Cero fichas, cero campos apuntando, y ECK se llevó sus
  dos tablas al borrarlo.
- **Siete vistas**: `tec_order_material_calculation` y su duplicado, la del *Excel Lover*, las dos
  del núcleo que nunca se usaron (`archive` y `glossary`), `dev` —sin página ni bloque— y
  `material_calculation_inventory_based`, cuyo bloque no estaba colocado en ninguna parte. Se
  llevaron además cuatro traducciones al tailandés que nadie sabía que existían.
- **Siete almacenes de campo en nodos**: `field_image_browser`, `field_files_over_ajax` y los cinco
  `field_dth_*` que dejó el tema DXPR. Sin instancias, cero filas y cero en revisiones.
- **Catorce tablas**: las trece `tmp_b44c2btec_gui*` y una más que apareció por el camino.
- **La cuenta `devT`**, con rol de administrador y correo en un dominio que parece una errata del
  del dueño. Bloqueada desde junio de 2024 y sin nada colgado. Una llave maestra que no es de nadie
  conocido no se deja bloqueada, se quita.

**Las trece tablas temporales no eran basura anónima.** Eran las tablas de `tec_gui` con cincuenta
fichas dentro, aparcadas por una actualización de tipo de entidad que se quedó a medias hace dos
años. Se comprobó antes de tirarlas que **las tablas de verdad existen y están vacías**, y que Drupal
no ve ninguna ficha: o sea que esos cincuenta registros eran invisibles para el ERP desde el día en
que se quedaron ahí.

**Y un susto que no lo era.** Al borrar los siete almacenes se purgó la cola a mano para no dejarle
el trabajo al cron, y el contador de pendientes se quedó clavado en dos durante doce vueltas. Parecía
un atasco. No lo era: **estaba mirando el contador equivocado.** Los pendientes son *definiciones* de
campo, y una definición no desaparece hasta que se ha vaciado su tabla entera; mientras, las filas
sí bajaban de doscientas en doscientas sin que se viera. Eran unas 4.650 filas de
`ai_interpolator_status`, restos de los experimentos de IA. Contando filas en vez de definiciones, la
cola se vació en cinco vueltas.

Lo que sí quedó al descubierto al vaciarla: **una tabla de campo borrado que ya no pertenecía a
ninguna cola**, con diez filas de `field_tec_gui_product`. Ningún cron la iba a mirar jamás, porque
su definición ya se había purgado en su día y la tabla sobrevivió. Se comprobó que las diez fichas a
las que apuntaba no existen y se tiró. El mismo campo sigue vivo en otro subtipo, con su tabla
aparte, que no se ha tocado.

**Un hallazgo que cambia el plan de `tec_gui`.** Está en la lista de deuda técnica como "tipo de
entidad abandonado", y es más que eso: `field_tec_gui_product` es un campo **obligatorio** de las
líneas de pedido de venta. No se puede retirar `tec_gui` sin tocar antes eso, así que la ficha de
deuda técnica está mal planteada y hay que rehacerla.

**Las dos carpetas de peso muerto, fuera**: `tec_crm` y `tec_brands`, 51 ficheros y 111 KB, sin una
sola línea de PHP —solo configuración exportada al estilo *features*—. Y una corrección de lo que se
escribió esta mañana: **no eran un riesgo**, como dije en el commit del cron. El módulo `features` no
está instalado y las dos dependen de `ai_interpolator`, que se desinstaló el 13 de agosto, así que
Drupal se habría negado a activarlas. Eran peso muerto, no una bomba. El guardián del cron sigue
teniendo sentido, pero por otro motivo: un clic en la interfaz o una base de datos vieja restaurada.

### 2026-08-14 — Quince avisos por carga, callados con un `?? NULL`, y por fin probado con un pedido de verdad

`views_simple_math_field` leía el primer valor de un campo sin comprobar que hubiera alguno:
`$entity->get($field)->getValue()[0]['value']`. Una línea de pedido sin cantidad devuelve un array
vacío, así que `[0]` no existe y `['value']` se lee sobre `NULL`: dos avisos por cada campo vacío y
por cada fila, quince en una sola carga de la tabla de líneas. Y un pedido nuevo empieza con **todas**
las cantidades vacías. No rompía nada, la página respondía 200, pero el registro es donde se buscan
los fallos de verdad y eso los enterraba.

El arreglo es `?? NULL` al final de la línea. El valor acaba siendo nulo con parche y sin él, o sea
que el cálculo se comporta exactamente igual; lo único que cambia es el ruido.

**Lo que costó no fue el arreglo, fue conseguir aplicarlo.** El parche falló dos veces, por dos
motivos distintos y los dos reutilizables:

- **Estaba en UTF-16.** Es el fallo recurrente del editor en este proyecto, y hasta hoy solo se había
  cazado en los `.php`. Un `.patch` en UTF-16 no lo lee ni `patch` ni `git apply`, y el mensaje de
  Composer no dice por qué: solo *"Cannot apply patch"*. **Al escribir un parche hay que comprobar la
  codificación igual que en los guiones.**
- **La cabecera del hunk mentía por uno.** Declaraba once líneas nuevas donde había diez, y GNU
  `patch` respondió *"malformed patch at line 38"*, señalando la última línea de contexto en vez del
  número mal contado. Se diagnostica en un segundo con `patch --dry-run`, que dice bastante más que
  Composer.

**Y por fin se ha podido probar de verdad.** Comprobar que un aviso ha dejado de salir no se puede
hacer leyendo el código: hay que pedir la pantalla. Desde la limpieza no había ningún pedido con el
que pedirla, así que `scripts/probar-el-parche.php` **se fabrica uno**: material, talla con
BoM, pedido de venta y dos líneas, una a propósito **sin cantidad**, que es el caso que
disparaba los avisos. Después pide `/o/draft/<pedido>` y `/o/pf/<pedido>/print`, mira el registro y lo
borra todo. Resultado: las dos pantallas responden 200 y el registro se queda **exactamente a cero**.

Eso deja además resuelto lo más difícil del punto pendiente de devolverle a la prueba de humo las
seis pantallas que perdió: **la receta de qué hay que fabricar ya está escrita y probada** en ese
guion. Lo que falta es llevarla a `cargan-las-paginas.php` y añadir el pedido de compra y el cliente,
que son las otras tres pantallas.

### 2026-08-14 — El cron desarmado antes de encenderlo: dos llaves fuera y los trece trabajos revisados

El cron de este sitio **no ha corrido nunca**. Encenderlo está en la lista de "antes de que entren
los empleados", y hasta hoy eso significaba encender de golpe trece trabajos que nadie había mirado.
Uno de ellos ya había hecho daño: **`backup_migrate` volcaba la base de datos entera a la carpeta
privada del proyecto cada noche y guardaba treinta copias**. Es de donde salieron los 178 volcados y
los 111 MB que hubo que sacar a mano el 12 de agosto. Seguía encendido, esperando.

**Apagado, y por la bandera correcta.** Aquí había una trampa que merece la pena anotar:
`backup_migrate_cron()` no comprueba nada, ejecuta **todos** los horarios uno detrás de otro
(`backup_migrate.module:232`). La comprobación está dentro, en `Schedule::run()`, y lo que lee es
`$this->get('enabled')` (`Schedule.php:93`), **no** el `status` de la ficha de configuración. Son dos
banderas distintas en el mismo sitio, y apagar la que no es habría dado una sensación de seguridad
falsa. Se apagó `enabled`, y de paso el trabajo `backup_migrate_cron` de Ultimate Cron, que es la
misma llave un piso más arriba. Las copias las hace `scripts/copia-de-seguridad.ps1`, que hace más
—código, ficheros, historial de Git y verificación— y escribe **fuera** del proyecto.

Detalle tranquilizador que se comprobó de paso: ese trabajo tiene `catch_up: 0`, así que no habría
disparado al encender el cron, sino a las 3 de la mañana siguiente. Se habría descubierto por la
mañana, con el volcado ya dentro.

**Segunda llave fuera: `eca_base_cron`.** Se despertaba cada quince minutos, 96 veces al día, y
**ninguno de los 36 procesos de ECA escucha el evento de cron**. Trabajo cero, ruido cien. Se vuelve
a encender el día que haga falta un automatismo periódico de verdad, por ejemplo el aviso de stock
bajo.

**Y una que no era un interruptor sino un hueco.** `dblog.settings` **no existía** en la
configuración activa. No es que el límite estuviera bajo: no estaba. `dblog_cron()` lee ese valor y,
si no hay nada, `if ($row_limit)` es falso y **no recorta nunca**, o sea que el registro habría
crecido sin freno. Ahora está escrito y exportado, en **100.000 filas**. Lo de fábrica son mil, que
en un ERP recién arrancado es media mañana, y el registro de las primeras semanas de uso real es
justo donde se van a buscar los fallos.

**Lo que dijo la consulta al purgador de campos.** `field_cron` es el único de los trece que borra
datos, así que antes de dejarlo suelto se preguntó qué tenía en la cola: **dos almacenes y cuatro
campos**, y todos son restos de lo mismo. `ai_interpolator_status` en `tec_units`, `tec_colors` y
`tec_bom_item` —los experimentos de IA de Oscar, cuyo módulo se desinstaló el 13 de agosto— y
`field_tec_edit_product`. Llevan ahí desde que se borraron, porque el cron nunca pasó a recogerlos.
O sea que `field_cron` no es un riesgo: es el que limpia. Se queda encendido.

Los trece, con la decisión de cada uno:

| Trabajo | Cuándo | Decisión |
|---|---|---|
| `backup_migrate_cron` | 3:00 cada día | **apagado**, volcaba la base dentro del proyecto |
| `eca_base_cron` | cada 15 min | **apagado**, ningún proceso escucha |
| `field_cron` | cada 3 h | se queda: purga los seis restos de la IA |
| `file_cron` | 0:00 cada día | se queda; no tocaba los 315 huérfanos, que se borraron a mano |
| `system_cron` | cada 6 h | se queda: cachés, lotes viejos, control de inundación |
| `update_cron` | por defecto | se queda: es el que avisa de las alertas de seguridad |
| `dblog_cron` | por defecto | se queda, ya con límite escrito |
| `layout_builder_cron` | 0:00 cada día | se queda: borra borradores de diseño olvidados |
| `locale_cron` | domingos 0:00 | se queda: no hace nada, y está bien así (ver la corrección) |
| `ultimate_cron_cron` | 0:00 cada día | se queda: limpia sus propios registros |
| `node_cron`, `feeds_cron`, `feeds_log_cron` | — | ya estaban apagados, y bien |

**No queda ninguna cosa abierta**, y las dos que se apuntaron aquí eran falsas las dos. La primera
es una corrección de lo que se escribió esa misma tarde. Dije que `locale_cron` se despertaría los domingos a descargar traducciones de tailandés para
los 146 módulos y a engordar `locales_source` y `locales_target`, y que antes de encender el cron
había que decidir si el ERP iba a ser multiidioma. **No es verdad, y se comprobó en el código del
núcleo el 14 de agosto por la noche.** El ajuste "cada cuánto comprobar si hay traducciones nuevas"
está en **0, que significa nunca** (`locale.settings`, `translation.update_interval_days`), y
`LocaleCronHooks::cron()` lo consulta antes de mover un dedo: si es cero, el trabajo se despierta y
se vuelve a dormir sin tocar la red ni la base de datos. O sea que se queda encendido tal cual, sin
que haya nada que decidir. El detalle de qué hay montado de idiomas y por qué, más abajo en esta
misma sección.

Y la segunda tampoco era una decisión que bloqueara el cron, aunque aquí quedó escrita como si lo
fuera. `file_cron` no iba a borrar los 315 ficheros huérfanos, precisamente porque
`make_unused_managed_files_temporary` está en `false`: el cron pasaba de largo por su lado. Se
borraron esa misma noche, pero por limpieza y no porque el cron lo pidiera.

**El guardián.** `comprobacion.php` pasa de 42 a **46 comprobaciones**: los ocho trabajos
encendidos, que los dos desarmados sigan apagados, que ningún horario de copias esté encendido y que
el límite del registro siga escrito. No es paranoia: `tec_crm` y `tec_brands` siguen en el disco con
49 copias viejas de configuración dentro, y un `features:import` despistado puede resucitar
cualquiera de estas cosas sin decir nada. Comprobación 46/46 y prueba de humo en verde después de
todo.

### 2026-08-14 — El ratón ya enseña la pantalla y no el nodo: la portada enlaza por campo, y las redirecciones fuera

Media hora después de poner las cuatro redirecciones, el dueño vio el precio del apaño: **al pasar
el ratón por los cuatro iconos nuevos salía `/node/11` en vez del destino**, mientras que en los
antiguos salía `/p`, `/i`, `/o`. Queja legítima, y con explicación exacta.

**La portada siempre había enlazado al nodo, nunca al destino.** En los siete antiguos el nodo *es*
la pantalla y tiene alias bonito, así que el ratón enseñaba una ruta bonita por casualidad. Los
cuatro nuevos son de otra naturaleza —la pantalla es una ruta de `tec_production`, no vive dentro
del nodo— y esos nodos no tienen alias. Y la redirección salta **en el servidor, después de
pinchar**: en el HTML ponía `/node/11`, así que el navegador decía la verdad.

**Arreglado como estaba escrito que había que arreglarlo**, el mismo día que se escribió: un campo
de enlace, `field_tec_target`, en el tipo de contenido *Landing page*. Obligatorio, solo rutas
internas, sin texto de enlace. Relleno en las once portadas, y la vista de la portada pinta ahora
el icono y el título como enlace **a ese campo** en lugar de al nodo.

Tres detalles que hacen que funcione, y que la próxima vez ahorran media hora:

- El campo se añade a la vista **primero y excluido**, porque una reescritura de Views solo ve los
  campos que van **antes** que ella.
- Su formateador es *"solo la URL, en texto plano"*. Un campo de enlace pintado normal daría un
  `<a>` en vez de una ruta, y meterlo dentro de un `href` sería un desastre. Con esa opción da
  `/o/queue` limpio. El núcleo contempla el caso literalmente: *"Tokens might have resolved URL's,
  as is the case for tokens provided by Link fields"* (`FieldPluginBase::renderAsLink`, línea 1472).
- Las siete primeras rutas **no se escriben a mano**: el guion las saca del alias real de cada
  nodo, así que el campo dice lo que dice Drupal y no se desfasa si alguien cambia un alias. Solo
  las cuatro de código están escritas, porque su ruta la define `tec_production.routing.yml`.

**Y fuera la muleta: las cuatro redirecciones borradas**, de seis a dos. Quedarse con ellas habría
sido peor que borrarlas: dentro de un año nadie sabría por qué `/node/14` salta a otro sitio.
Efecto colateral asumido: si alguien entra a `/node/11`–`/node/14` a pelo vuelve a ver la página
del icono y el número. Ya no hay ningún enlace que lleve ahí.

**Comprobado lo que un humano ve.** Pedido `/start` como usuario 1 y listados los enlaces uno a
uno: once iconos, los once apuntando a su pantalla, las once rutas existen, y cada uno con sus dos
enlaces (imagen y título). El formulario de una portada abre con el campo relleno.

**Guardián nuevo en la comprobación del ERP**, porque el fallo anterior duró meses sin que nada se
quejara: *"las portadas dicen qué pantalla abren"* y *"y esa pantalla existe"*. 42 de 42. Y el
campo obligatorio impide que nazca una portada sin destino, que era la otra mitad del problema.

Queda en `scripts/destino-de-las-portadas.php` (crea el campo, lo rellena y quita las
redirecciones; se puede volver a pasar sin miedo) y en `scripts/a-donde-apuntan-los-iconos.php`
(la comprobación de lo que ve el ratón).

### 2026-08-14 — Super BOM retirado, y cuatro iconos del home que no llevaban a ninguna parte

Super BOM sobraba: lo que hacía lo hace ya el tablero de stock. Antes de tocarlo había que
responder a una sola pregunta, quién lo usa, y ahí apareció lo interesante.

**El barrido en busca de referencias tenía un punto ciego, y era justo donde estaba la respuesta.**
Se buscó *superbom* en las **1.335 columnas de texto de las 447 tablas** de la base y de contenido
salió limpio. Ese resultado no valía nada: **las portadas del ERP se montan con Layout Builder, y
ese campo se guarda serializado en una columna binaria**, igual que `config.data`. O sea que el
único sitio donde podía vivir el enganche era el único que un `LIKE` no mira. Hubo que abrir las
secciones con la API de Drupal para verlo.

La receta buena, para la próxima retirada —Patrones—: **el mapa de portadas más un `grep` sobre
`config/sync`**, nunca una búsqueda de texto sobre la base. Queda en
`scripts/que-hay-en-las-portadas.php`, que dice qué bloques lleva cada portada dentro.

**Lo que había, ya con el mapa delante:**

- **`tec_superbom`** no la usaba nadie. Llevaba muerta quién sabe cuánto.
- **`tec_superbom_ii`**, bloque `block_1`, tenía una sola referencia: el diseño del **nodo 10**, que
  es la propia página `/bom`. Nada en ECA, nada en el código propio, ninguna otra vista, ningún
  otro nodo.

Así que se borró el nodo, y con él se fue su diseño y el bloque. **La entrada de menú la borró el
núcleo solo**, en `MenuLinkContentHooks::entityPredelete`: siete enlaces donde había ocho. Después
las dos vistas. El icono desapareció del home igual que desapareció el de Patrones.

**Dos usuarios menos para `simple_popup_views`**, que es uno de los dos módulos sin versión para
Drupal 11 y vive de un parche nuestro. Las dos vistas de Super BOM lo usaban. Pasa de diez vistas
a ocho; sigue siendo deuda, pero la superficie mengua. Por el camino se vio una de esas ocho que
huele a resto: `views.view.duplicate_of_tec_order_material_calculation_terms_copy`, un duplicado
con nombre de duplicado. Sin comprobar.

**Un resto que se deja a propósito.** En
`field.field.tec_inventory.tec_bom_item.field_tec_color_swatch_vf.yml` queda una línea
`tec_superbom: '0'`: es la lista de casillas de "vistas permitidas" de un campo de vista, donde
figuraba con un cero, o sea **no** permitida. No era dependencia y Drupal no la limpia al borrar la
vista. Tocar ese campo para quitar una línea inocua es más riesgo que la línea.

**El segundo hallazgo, que salió de la misma consulta: cuatro de los catorce iconos del home no
llevaban a ninguna parte.** *Orders on Queue*, *Production Log*, *Production Report* y *Stock
Control* apuntaban a `/node/11`–`/node/14`. Esos cuatro son los únicos nodos **sin diseño propio**,
así que caían al diseño por defecto del tipo de contenido, que pinta el cuerpo (vacío), el icono
otra vez y el **número de orden**. Nada más. Las pantallas de verdad no son nodos, son rutas del
módulo `tec_production`: `/o/queue`, `/production/log`, `/production/report` y `/stock`.

**Y aquí la lección, que es más valiosa que el arreglo.** La entrada de ayer celebraba que la
prueba de humo por fin pedía las doce portadas y que *"todas responden 200"*, `/node/11` a
`/node/14` incluidos. Respondían 200 **y eran callejones sin salida**. Un 200 dice que el servidor
no se ha roto; no dice que la página sirva para algo. Es el mismo tipo de fallo que los botones de
crear escondidos: parece correcto hasta que pinchas.

Arreglado con **cuatro redirecciones**, que es lo que el sitio ya usa para `/customers` y
`/products`. Dos decisiones dentro:

- **Son temporales (302), no permanentes.** El arreglo bueno es un campo de enlace en el tipo de
  contenido y reescribir la salida de la portada. Si esto fuera un 301 se quedaría pegado en el
  navegador de todos y costaría más quitarlo que ponerlo.
- **Son contenido, no configuración.** Viven en la base de datos y viajarán al servidor con la
  base, no con Git. Si algún día se despliega de otra manera, hay que acordarse de ellas.

Comprobado con `curl` de verdad: `/node/11` devuelve `302` con `Location: /o/queue`, `/node/14` con
`/stock`, y `/bom` ya devuelve `404`. **Ojo con un detalle de la prueba de humo**: ahí esas cuatro
siguen apareciendo como 200, porque las peticiones internas no pasan por el vigilante de
redirecciones —solo actúa en la petición principal—. No es un fallo de la prueba, pero hay que
saberlo para no volverse loco.

Recuentos de la comprobación al día: nodos 12 → **11**, enlaces de menú 8 → **7**, redirecciones
2 → **6**. Sale 40 de 40, y la prueba de humo 30 páginas sin problemas. El icono del nodo 10
(fichero 306) se suma a los huérfanos: el total de ficheros sigue en 315 porque Drupal no los
borra, simplemente hay uno más que no usa nadie.

Copia antes de tocar en `C:\laragon\backups\antes-de-retirar-superbom\` (64 MB). Lo que se hizo
está en `scripts/retirar-superbom.php`, con las razones dentro.

> Aviso para quien lea esto suelto: **las cuatro redirecciones ya no existen**. Duraron media hora.
> El arreglo bueno llegó el mismo día y está en la entrada de arriba.

### 2026-08-14 — Diez botones de crear escondidos, y el CRM sin manera de empezar

El dueño entró a mirar el ERP vacío y encontró lo primero que había que encontrar: en Contactos,
Clientes y Proveedores **no había botón para crear nada**. Sin cliente no hay pedido de venta y
sin proveedor no hay orden de compra, así que el ERP entero estaba cerrado por ahí.

**Los botones estaban puestos.** Los cuatro, bien escritos, apuntando a las rutas correctas. Lo
que estaba mal era una casilla: en Views, las zonas de cabecera tienen un *"Display even if view
has no result"* que **viene apagada por defecto**, y apagada significa que la zona desaparece
cuando la consulta no trae filas (`AreaPluginBase::isEmpty()`, línea 122 del núcleo). O sea el
círculo vicioso perfecto: **el botón de crear el primer contacto solo aparecía cuando ya había
contactos.**

Llevaba así desde siempre y nadie se había enterado, porque con 22 fichas de prueba dentro la
lista nunca estaba vacía. Lo destapó el borrado de la noche anterior.

**Y no era un botón, eran diez, en siete pantallas.** El barrido de `empty: false` seguido de un
enlace de crear los saca todos. Los que bloqueaban:

- Las **tres del CRM** (`views.view.tec_crm_customers`): Contactos, Clientes y Proveedores, con
  sus botones *+ Organization* y *+ Person*.
- El bloque de **materiales del proveedor** (`views.view.tec_inventory`, "CRM Supplier Inventory
  block"): al abrir un proveedor nuevo no había manera de añadirle su primer material.

Los que no bloqueaban, y esto es lo interesante: **pedidos, productos y la pantalla principal de
inventario ya tenían la casilla marcada**. El patrón correcto se conocía y se había aplicado en
la mitad del ERP. No es un olvido de diseño, es un despiste repetido.

Arreglado lo que bloqueaba, más la pantalla de colores por consistencia. Y se le puso a las tres
del CRM el texto que faltaba para cuando no hay nada, en inglés: *"Nothing here yet. Use the
buttons above to add an organization or a person."* Va **una sola vez en el display por defecto**
y las tres pantallas lo heredan, en lugar de tres textos casi iguales: así se lee bien en las
tres y hay un solo sitio que mantener.

**Una errata de una letra, en un sitio que no importa.** El botón de añadir color de
`views.view.tec_colors` apuntaba a `tec_colorss`, con dos eses, o sea un 404. Corregida. Está en
un display cuya ruta es **`/asas`** —teclado aporreado— que es una pantalla de pruebas que nadie
usa y que se puede borrar; aparece en la prueba de humo desde el principio y ahí sigue.

**Un efecto secundario que conviene conocer antes de verlo.** Al guardar una vista por primera
vez bajo Drupal 11, su exportación se normaliza: los `1` y `0` de `sortable`, `sticky`,
`override` y `empty_column` pasan a ser `true` y `false` de verdad, y alguna clave cambia de
sitio. Resultado: **un cambio de una palabra produce un diff de sesenta líneas**. No es daño y no
hay que pelearse con él, pero la primera vez asusta y hace dudar de si se ha tocado algo más.

**Y lo que hay que arreglar de verdad es por qué esto no lo encontró una máquina.** La prueba de
humo descubre las vistas por su ruta propia, y las portadas del ERP no tienen ruta propia: son
**nodos** con bloques de vista dentro. Así que llevaba meses sin pedir ni una de las doce
pantallas por las que se entra a todo. Ahora las pide todas: de 20 pantallas a **31**, y con eso
entraron `/p`, `/i`, `/o`, `/c`, `/c/customers`, `/c/suppliers`, `/cl`, `/bom` y cuatro nodos que
no tienen alias (`/node/11` a `/node/14`). Todas responden 200.

Queda un límite honesto: la prueba comprueba que la página responde, **no que el botón esté
dentro**. Eso se verificó a mano esta vez, pidiendo las tres pantallas como usuario 1 y buscando
los enlaces en el HTML. Si algún día hace falta automatizarlo, el sitio es la comprobación del
ERP, no la de humo.

### 2026-08-14 — El ERP se queda vacío: 7.003 fichas y 892 términos fuera, y el ERP en verde después

El contenido de prueba ya no está. Sesenta y siete segundos de reloj para lo que llevaba semanas
en la lista, y ni un solo error. Pero lo que se aprendió por el camino vale más que el borrado.

**El recuento real era la mitad de lo que decía este backlog.** Aquí ponía "casi 15.000
registros" desde el 12 de agosto. Eran **7.003 fichas de contenido y 892 términos**. La cifra
vieja venía de sumar los 13.615 movimientos de inventario que se contaron una vez en la pantalla
de inventario, y esa pantalla cuenta filas de una vista, no fichas: multiplicaba. Se descubrió al
contar de verdad con `scripts/que-hay-dentro.php`, antes de tocar nada, y es el argumento a favor
de contar antes de planificar: el plan que se había hecho para 15.000 registros incluía lotes,
pausas y reintentos que no hacían falta.

**El orden importaba de verdad, y hubo que averiguarlo en tres pasadas.** El primer mapa de
dependencias salió de la configuración —qué campo apunta a qué—, y estaba incompleto: la
configuración dice qué *puede* apuntar, no qué apunta. Hicieron falta dos guiones más
(`quien-apunta-a-que.php` y `dos-riesgos.php`) leyendo las tablas de datos para encontrar tres
cosas que no estaban en el plan: **catorce registros de producción**, **cuarenta y siete
importaciones** y **treinta y una redirecciones** del servidor viejo. Ninguna aparecía en la
lista original. Los dieciséis pasos finales se ejecutaron en cascada, de las hojas al tronco:
producción, BoM de línea, BoM, movimientos, líneas, pedidos, variaciones,
productos, fichas de CRM, materiales, nodos, redirecciones y por último los vocabularios.

**El fallo de la bandera silenciosa no se activó ni una vez.** Era el riesgo serio: en este ERP,
borrar un material que está en uso deja puesta una bandera de bloqueo, y una línea con esa
bandera **deja de recalcular sin avisar a nadie**. Por eso los 29 procesos de ECA se apagaron
antes de empezar y se volvieron a encender al acabar, y por eso el orden iba de las hojas al
tronco: cuando le llegó el turno al material, ya no le colgaba nada. Al terminar, en todo el sitio
quedaba **una sola bandera puesta, `tec_eca_gui_lock`**, que es la que debe estar en reposo.

**Y apagar ECA tiene un precio que nadie cuenta: 7.033 errores en el registro.** El módulo sigue
escuchando aunque sus procesos estén apagados, así que cada entidad borrada disparó el motor, que
no encontró nada suscrito y lo anotó como error. No rompe nada y es inevitable mientras se apague
así, pero conviene saberlo antes de verlo y pensar que algo ha ido mal.

**El registro entero, a la basura, y con motivo.** Tenía **15.439 líneas desde el 4 de mayo de
2024**: 7.822 errores de ECA, las 505 emergencias falsas de la errata que se arregló el día
antes —el arreglo evita las nuevas, no borra las viejas—, 2.053 avisos de cron, 308 de
`upgrade_status`, que ya ni está instalado. Todo eso es diagnóstico de una época de pruebas cuyos
datos acaban de desaparecer. Un registro donde el próximo fallo de verdad va a aparecer como la
línea 15.440 no es un registro, es un pajar. Vaciado. Si algún día hiciera falta, está entero en
la copia de seguridad de esa noche.

**Los 869 materiales salieron a CSV antes de morir, y de ahí salió una respuesta gratis.** El
fichero está en `C:\laragon\backups\materiales-antes-del-borrado\materiales.csv`, 57 columnas,
162 KB, fuera del proyecto porque lleva costes y proveedores dentro. Al escribirlo se contó
cuántos materiales traían cada campo relleno, y eso contestó de golpe una pregunta que llevaba
semanas abierta: **de los dos campos de proveedor, el que se usaba es `field_tec_vendor`** —38
materiales— y `field_tec_suppliers` estaba **vacío en los 869**. Se iba a decidir a ojo y la
decidieron los datos. El resto del retrato: solo ocho campos rellenos en todo el catálogo, precio
en 50 materiales, coste en 15, plazo de entrega en 3, SKU en ninguno.

**La comprobación del ERP cambió de oficio.** Vigilaba recuentos —4.773 BoM, 869
materiales, 18 pedidos—, y contra una base vacía eso no avisa de nada. Ahora vigila que los
**dieciséis tipos de contenido sigan declarados** en las seis entidades, que los vocabularios que
se quedan tengan lo que tenían, y que los automatismos reaccionen. Esa última parte era la única
que probaba algo de verdad, y era también la única que dependía de los datos de prueba: se
apoyaba en un material cualquiera de los 869 y en una línea de pedido con precio. **Ahora se
fabrica sus propios datos, los usa y los borra**, y se ha comprobado que dos pasadas seguidas dan
lo mismo. Cuarenta comprobaciones, cuarenta en verde.

Fabricar esos datos enseñó dos cosas sobre el ERP que no estaban escritas en ningún sitio:

- **Una línea de pedido con precio y cantidad no calcula nada, y hace bien.** El primer maniquí
  llevaba solo eso, y el total salía vacío. Parecía un fallo grave. No lo es: el proceso solo
  escucha el evento de actualizar, y todo lo que calcula gira alrededor del BoM de la
  talla. Una línea que no cuelga de una talla no es un caso real y el proceso la deja en paz. Con
  la talla puesta, cambiar la cantidad a 6 con precio 12,50 da **75,00** al primer intento.
- **Al guardar una línea, ECA crea por su cuenta un BoM de línea y lo engancha.** Nadie lo
  pide y no se ve en ninguna pantalla. La primera sonda dejó uno huérfano y la comprobación
  siguiente habría fallado por encontrar contenido donde debía haber cero, sin pista de por qué.
  Ahora se va a buscar y se borra.

**La prueba de humo perdió seis pantallas y ahora al menos lo dice.** Al quedarse sin datos no
encuentra con qué pedir las vistas que llevan un identificador en la dirección, así que se las
salta: `o/draft/%`, `po/draft/%`, las dos de imprimir, el PDF y `tec_crm/%/reorder`. Pasó de
mirar veintiséis a mirar veinte **y el resumen seguía diciendo "todas cargan"**, que es la clase
de frase con la que se despliega tranquilo sin motivo. Y la peor de las seis es `o/draft/%`,
justo la que se rompió el día antes. Ahora las nombra una por una. Fabricarle un pedido de
mentira, como hace ya la comprobación, queda apuntado arriba.

**Donde se paró a propósito: Marcas y Patrones.** Sus términos se borraron, pero los vocabularios
siguen ahí vacíos con sus campos, tres vistas y sus formularios. Al ir a retirarlos apareció el
motivo para no hacerlo esa noche: **los dos procesos de duplicar producto copian
`field_tec_brand` y `field_tec_pattern`**, así que borrar los campos rompe el duplicado de
productos. Borrar datos y desmontar configuración son dos trabajos distintos, y mezclarlos a las
tres de la mañana era la manera de no saber después qué había roto qué.

Lo que se queda dentro: 32 colores, 22 tipos de material, 23 tipos de producto, 14 tallas, 13
unidades, los 3 tipos de contacto —intactos, que ahí estaba el fallo que costaba caro—, 12 nodos
de navegación, 8 enlaces de menú, 2 redirecciones y 3 importaciones. Y de paso se limpiaron
**seis términos duplicados** que había creado el importador con sus erratas, entre ellos un color
"Blue " con un espacio detrás.

Guiones nuevos, todos en `scripts/`: `que-hay-dentro.php`, `quien-apunta-a-que.php`,
`dos-riesgos.php`, `borrar-datos-de-prueba.php` y `exportar-materiales.php`.

### 2026-08-14 — Las carpetas sobrantes, y un volcado de la base de datos que se descargaba sin contraseña

Lo que iba a ser un borrado de dos minutos ha destapado tres cosas, una de ellas de seguridad.

**Las dos carpetas de la restauración, fuera.** `config/sync.pre-restore-20260810023747` y
`modules/custom.pre-restore-20260810023746`: 1.382 ficheros, 6,7 MB. Antes de borrarlas se han
comprimido enteras en `C:\laragon\backups\restos-pre-restore-20260810.zip`, 1,2 MB, verificando que
dentro están los 1.382 ficheros. Se ha archivado en vez de borrar por lo que salió al compararlas
con lo actual, que es el motivo de no fiarse de la nota que decía "restos ya superados".

**Había siete ficheros que no existían en ningún otro sitio.** No eran basura: eran una función
entera del inventario. Un selector propio de materiales para el BoM —el plugin
`tec_inventory:bom_material`, su controlador, su JavaScript, su enrutado y sus servicios— más un
`menu-accent.css`. Nunca estuvieron en Git: el primer commit del repositorio, del 9 de agosto, se
llama precisamente *"Baseline snapshot before tec_inventory redesign"* y no los contiene, así que
eran trabajo empezado esa noche durante el rediseño y la restauración del 10 de agosto se lo llevó.
Comprobado que su desaparición no dejó nada roto: **ni la configuración activa ni el código actual
mencionan ese plugin**, o sea que ningún campo está pidiendo un manejador que ya no existe. La idea
sí merece recordarse, porque el vocabulario de materiales tiene 869 términos y aquello era
justamente un autocompletado con lista de etiquetas ligera en caché y filtrado local en JSON.

**De los 62 objetos de configuración que solo estaban en la carpeta antigua, ninguno hacía falta.**
Dieciséis son ocho procesos ECA, y los ocho están **desactivados** y son clones —con `(clone)` en el
nombre— de dos automatizaciones que siguen vivas. El resto son restos de módulos ya desinstalados:
`comment`, `color`, `ds_extras`, `features`, `file_mdm`, `shield`, `pace`, y tres campos
`ai_interpolator_status` del interpolador de IA.

**El riesgo que nadie había visto.** La carpeta estaba *dentro* de `modules/`, y Drupal rastrea ese
directorio de forma recursiva buscando ficheros `.info.yml`. Es decir: había **dos copias de cinco
módulos con el mismo nombre**, y cuál gana depende del orden de rastreo. Se comprobó antes de borrar,
preguntándole a Drupal de qué ruta carga cada uno, y por suerte cargaba los actuales
(`modules/custom/...`). Pero era suerte, no diseño. Ese es el argumento de verdad para no dejar
copias de módulos dentro de `modules/`, por encima del sitio que ocupan.

**Y lo de seguridad: un volcado completo de la base de datos de febrero de 2024, descargable sin
credenciales.** En la raíz del sitio había una carpeta `private_` —con guión bajo al final, señal de
que alguien la "desactivó" renombrándola— y dentro
`backup_migrate/backup-2024-02-22T13-06-08.mysql.gz`. Esa carpeta **no** es el camino privado
configurado: el de verdad es `sites/default/private`. Y no tenía `.htaccess`. Pedirla por el
navegador devolvía **HTTP 200 y 660 KB**, es decir la base de datos entera, con su tabla de usuarios,
sin pedir nada. El detalle de por qué pasaba desapercibido: el `.htaccess` de la raíz de Drupal
bloquea una lista de extensiones en la que está `.yml` —de ahí el 403 al pedir un fichero de
`backups/`— pero **`.gz` no está en esa lista**.

Lo primero fue mirar si eso había viajado al servidor público, que es donde importaría de verdad:
`tec.actafight.com` devuelve **404** en las tres rutas probadas, así que la exposición por web era
solo local. El volcado se ha movido a `C:\laragon\backups\volcado-antiguo-2024-02-22\` —moverlo y no
borrarlo, porque es el retrato más antiguo que queda de la base de datos— y la carpeta `private_` se
ha borrado. Ahora la URL da 404. De paso se ha verificado que la carpeta privada de verdad sí está
cerrada: trae `Require all denied` y pedir un fichero real de dentro devuelve 403.

**Pero el volcado estaba dentro del repositorio, y eso no lo esperaba nadie.** Al borrar la carpeta,
Git marcó los dos ficheros como eliminados, o sea que estaban siendo seguidos. Entró en
`9cbcff83`, el **primer commit** del repositorio, y seguía en HEAD. El motivo es de una línea:
`.gitignore` excluye `sites/*/private`, que es la carpeta privada de verdad, pero nadie escribió
nada de `private_` con guión bajo. Así que 660 KB con la base de datos de febrero de 2024 se
subieron a GitHub el 12 de agosto, en el mismo repositorio privado.

Antes de decidir qué hacer con eso se ha mirado qué contiene de verdad, porque había una pregunta
incómoda: la purga del historial del 12 de agosto quitó la clave de OpenAI de los objetos de Git,
pero si la clave hubiera estado en la base de datos, este volcado la habría vuelto a meter.
**Descomprimido y rastreado, el resultado es limpio**: 6,4 MB, 161 tablas, y cero coincidencias con
la forma de una clave de OpenAI, de Google, de AWS o de GitHub, ni claves privadas SSH o RSA. Hubo
un susto de dos coincidencias `AIza...` que resultaron ser un `default_config_hash` de Drupal:
`Select-String` **no distingue mayúsculas si no se le pide**, y estaba encontrando `aiZa` en
minúscula. Con `-CaseSensitive`, cero. De datos personales hay poco: una sola sentencia `INSERT` en
`users_field_data` y nueve líneas con correos.

Los dos ficheros se han quitado del repositorio y se han añadido reglas a `.gitignore` para que
ningún volcado vuelva a colarse por la puerta del nombre. Lo que queda por decidir es si merece la
pena reescribir el historial otra vez para borrarlo de `9cbcff83`; la recomendación es **no**,
porque el repositorio es privado, dentro no hay ningún secreto y una reescritura obliga a un
`push --force` que ya se sufrió una vez.

**Y de camino aparecieron 111 MB más, estos sí bien guardados.** En
`sites/default/private/backup_migrate` había **178 volcados** de la base de datos, de febrero a mayo
de 2024, congelados desde que el cron dejó de disparar el programador de `backup_migrate`. Eran dos
tercios de los 173 MB de la carpeta privada. Del navegador no se accedían —comprobado—, así que no
eran un riesgo; el problema era que esa carpeta entra en las copias de seguridad, y cada copia subía
esos 111 MB a Drive. Se han movido a `C:\laragon\backups\volcados-2024-backup-migrate\`. Antes se
comprobó lo que había que comprobar: **cero** de esos ficheros están registrados en `file_managed`,
así que mover no deja referencias muertas. La carpeta de destino se ha dejado en su sitio y vacía,
porque el programador diario sigue **activo** en la configuración y volverá a escribir ahí en cuanto
se arregle el cron. La carpeta privada pasa de 172,9 MB a **61,6 MB**.

**Comprobado después de todo.** Git limpio, sin un solo cambio, porque las dos carpetas estaban
excluidas del repositorio. Caché reconstruida y prueba de humo: **38 páginas, 0 con problemas**.

### 2026-08-14 — Las 505 emergencias falsas del registro, y `upgrade_status` fuera

Dos cabos sueltos de los baratos, cerrados de madrugada después de confirmar el arreglo del error
500. Los dos han dado más información de la que prometían.

**La errata del registro no era un carácter, eran dos.** El proceso `process_rxuimsq` escribía su
mensaje de arranque con nivel 0, *Emergencia*, en vez de 7, *depuración*. Lo que aquí estaba
apuntado —cambiar un carácter en `config/sync/eca.eca.process_rxuimsq.yml`, línea 166— habría
funcionado justo hasta que alguien abriera el modelo en el editor de ECA, porque **la severidad
está duplicada**: una vez en la configuración del proceso y otra dentro del XML del BPMN, en
`eca.model.process_rxuimsq.yml`. Ese fichero es el dibujo, y el dibujo manda cuando se guarda desde
la interfaz. Arreglar solo uno de los dos es dejar una mina.

De paso salió el tamaño real del problema: **505 entradas de nivel Emergencia en el registro, y las
505 son ese mensaje**. Ninguna de verdad. Eso es la mitad de la mala noticia y la mitad de la buena:
significa que el ERP no ha tenido nunca una emergencia real, pero también que si la hubiera tenido
habría entrado en una lista de 505 falsas y nadie la habría visto.

Comprobado en marcha, que es la única forma de darlo por bueno: la última emergencia es de las
18:22 del 13 de agosto, y las tres entradas de ese mismo mensaje posteriores al arreglo —de las
00:24 del 14, generadas al pasar la comprobación— entran con nivel 7. El contador de emergencias
queda congelado en 505. Esas 505 viejas siguen en el registro; se pueden borrar con una consulta si
molestan, pero se han dejado porque el registro rota solo.

**`upgrade_status` desinstalado, y una decisión que no se podía esquivar.** El módulo ya había
cumplido. Lo que no era obvio es qué hacer con `update`, que estaba encendido solo porque
`upgrade_status` lo arrastra como dependencia, y que era la otra mitad de las dos exclusiones
permanentes que ensuciaban `config/sync`. Había que decidir algo sí o sí, porque desinstalar solo
uno deja la cuenta igual de turbia.

**Se queda `update`, y ahora a propósito.** Es el módulo del núcleo cuyo único trabajo es avisar de
las alertas de seguridad, es decir exactamente lo que le faltó a este sitio mientras acumulaba
ochenta y dos sin que nadie se enterara, una de ellas un CSRF crítico. Apagar el vigía justo después
de esa historia sería la lección equivocada. En Drupal 11 ya no instala nada, solo informa, así que
no añade superficie. Sus ajustes están exportados y el módulo sigue en `core.extension`, de modo que
**`config:status` dice por fin "no differences"**, algo que no pasaba desde antes de la subida. Si
algún día se quiere apagar, es un `drush pm:uninstall update` y bajar la cifra de la comprobación.

**La comprobación queda en 35 de 35, con cuatro cifras actualizadas.** La de módulos sube a 146,
porque `update` deja de contar como herramienta temporal y pasa a ser un módulo más; la lista
`MODULOS_TEMPORALES` se queda vacía pero el mecanismo se deja puesto, que en la próxima subida hará
falta otra vez. Y tres cifras de contenido suben —18 pedidos de venta, 101 líneas y 202 elementos de
BoM— porque son **los pedidos 755 y 756 creados a mano anoche** para comprobar que la pantalla
de líneas volvía a abrir, con ocho líneas cada uno. Se actualizan a propósito: una cifra que falla
siempre por un motivo conocido deja de servir para avisar de nada.

Y la trampa del UTF-16 volvió a picar, esta vez en `scripts/exportar-config.php`. Van tres sesiones
seguidas. El detector ya está en la rutina de trabajo —mirar si el segundo byte es cero antes de dar
nada por bueno— pero esto refuerza lo que hay apuntado más arriba: merece la pena montar el *hook*
de Cursor que lo convierta solo.

### 2026-08-13 — El error 500 al editar las líneas de un pedido: lo rompía un parche nuestro, no el módulo

La revisión visual posterior al salto a Drupal 11 dio con un 500 en `/o/draft/755`, la pantalla
donde se editan las líneas de un pedido, que es de las más usadas de la fábrica. Crear el pedido
funcionaba y publicarlo también; lo que moría era volver a entrar a tocarlo.

La causa inmediata era `views_entity_form_field` llamando a `getEntityTranslation()`, un método que
Drupal 10 tenía marcado como obsoleto y que Drupal 11 ha borrado. Lo interesante es de dónde salía
esa llamada. **El módulo publicado ya estaba corregido**: la 8.x-1.0 de drupal.org usa el nombre
nuevo, `getEntityTranslationByRelationship()`. Quien la revertía era nuestro propio parche.

**La trampa, que es lo que hay que recordar.** Ese parche nació el 12 de agosto, al reconciliar el
sitio con Composer: la carpeta del disco era una versión antigua con el parche de AJAX aplicado a
mano, y para no perderlo se sacó la diferencia con `git diff` contra el paquete oficial. El
problema es que un `git diff` no distingue entre lo que añadiste tú y lo que el proyecto arregló
mientras tu copia se quedaba quieta. Todo aparece como "cambio local". Así que el parche llevaba
dentro, además del AJAX, **una vuelta atrás a la versión vieja del método**, invisible mientras el
sitio corría en Drupal 10 porque allí el método viejo seguía existiendo. El día que se subió a la
11, la página cayó.

Se revisaron los trece parches del proyecto buscando lo mismo. Solo este se sacó de una carpeta
instalada; los otros doce vienen de issues de drupal.org o se escribieron a mano para un fallo
concreto, y ninguno revierte nada del núcleo. La trampa estaba aislada.

**Cómo queda el parche.** Ya no toca esas tres llamadas: deja las dos que el módulo trae bien y
corrige la tercera, que el propio módulo tiene mal (`getEntityTranslation($old, $row)`, que solo
salta al guardar y por eso nadie lo había visto). Se deja a propósito una diferencia con el paquete
oficial, apuntada en la cabecera: la versión publicada comprueba que `loadUnchanged()` haya
devuelto algo antes de usarlo y la nuestra no. Con filas que vienen de una vista la entidad siempre
existe, así que no da problemas, pero conviene saberlo si algún día se rehace el parche.

**Y un detalle de fontanería que costó una hora.** En el tercer bloque del parche hay una línea que
se quita y se vuelve a poner exactamente igual. Parece una tontería y lo es, pero convertirla en
línea de contexto normal —que es lo correcto— hace que **GNU `patch` rechace el bloque entero**,
mientras que `git apply` lo acepta sin pestañear. Y el que acaba aplicando los parches aquí es GNU
`patch`, porque `composer-patches` intenta primero `git apply` desde dentro de la carpeta del
módulo, que está dentro de un repositorio, y ahí Git resuelve las rutas contra la raíz del
repositorio, no encuentra los ficheros y responde `Skipped patch`. Composer lo toma por un fallo y
cae al respaldo. Peor todavía: GNU `patch` aplica los bloques que puede y deja un `.rej` con el que
falló, así que un parche a medias deja el módulo **mitad parcheado**, que es el estado más
confuso posible para diagnosticar. Se comprobó contra el paquete limpio, con la misma herramienta
que usa Composer: con el par se aplica, sin el par no. Queda escrito en la cabecera del parche para
que nadie lo "limpie" y lo vuelva a romper.

**La prueba de humo tenía un agujero, y taparlo mal es peor que no taparlo.** `cargan-las-paginas.php`
se saltaba a propósito las vistas con argumento, dando por hecho que las fichas de ejemplo ya las
cubrían. No las cubren: la ficha va a la dirección canónica de la entidad, y las pantallas de
trabajo del ERP son vistas con el identificador en la dirección. Por eso el 500 pudo sobrevivir un
día entero diciendo la prueba que todo cargaba.

El primer arreglo rellenaba el `%` con la entidad más reciente del tipo de la tabla base, y salió
mal de una forma instructiva: la vista de líneas se apoya en `tec_line_item`, así que metía un
identificador de línea donde va uno de pedido, y `/o/draft/4192` devolvía **200 pintando una tabla
vacía**. Con cero filas el fallo no aparece, porque el módulo que se rompía solo trabaja dentro del
bucle de filas. O sea, un aprobado falso, que es peor que no mirar. La versión definitiva pregunta
a la propia vista de qué tabla y columna sale el argumento, coge valores que están en los datos y
**ejecuta la vista antes de pedir la página**, aceptando solo los que traen filas. Ahora elige el
755 él solo, que es justo el pedido que fallaba. Treinta y ocho páginas, todas con 200.

De paso, el analizador de código obsoleto sobre este módulo: limpio. Lo único que señala es
`views_ui_build_form_url()`, que solo se llama al editar la vista desde la interfaz de Views, donde
ese módulo está cargado por definición, y un aviso de que `^10 || ^11` no servirá para Drupal 12.

### 2026-08-13 — El `settings.php` del servidor, revisado: no había ningún agujero, y de paso se aprende a leerlo sin contraseña

La sospecha era razonable y resultó infundada. `rebuild_access` está en **`FALSE`** en el
servidor, así que nunca hubo nadie pudiendo vaciar las cachés del ERP sin identificarse. El
fichero de allí no es una copia del de local: se generó desde cero durante el despliegue del 12
de agosto, con los cinco ajustes de producción puestos a mano y explicados uno por uno. Mide
1.569 bytes frente a los 37.314 del de local, que arrastra todos los comentarios del original de
Drupal.

Ya que se entraba a mirar, se comprobaron los cinco de una vez: `rebuild_access` en `FALSE`,
`update_free_access` en `FALSE`, dominio de confianza únicamente `erp.anvfightgear.com`,
archivos privados en `/var/www/erp-private` —fuera de la carpeta que sirve Apache— y errores al
registro en vez de a la pantalla del cliente. Los cinco, correctos.

**Lo que costó, y cómo se resolvió, que es la parte reutilizable.** El fichero es
`root:www-data` con permisos `640`: el usuario con el que se entra por SSH (`david`) no lo puede
leer, `root` no admite entrar directamente por SSH, y `sudo` pide una contraseña que solo tiene
el dueño. Y desde fuera no sirve de nada pedir `/core/rebuild.php`, porque **redirige igual en
los dos casos**: la única diferencia es que, si estuviera abierto, la petición vaciaría de
verdad las cachés del sitio. O sea que la prueba obvia solo distingue haciendo el daño que
quieres descartar, y en un servidor de 2 GB sin memoria de intercambio eso puede acabar en un
sitio caído que además no podríamos levantar sin la contraseña.

La salida es que **el código del ERP pertenece a `david`**, así que se puede dejar un `.php` de
diez líneas en la raíz, pedirlo por el navegador y dejar que lo ejecute Apache, que sí es
`www-data` y sí puede leer `settings.php`. El fichero solo responde si se le pasa un testigo
aleatorio en la dirección, imprime los cinco ajustes y ni menciona la contraseña de la base de
datos ni el `hash_salt`. Se borra en el mismo paso y se confirma con un 404 que ya no está.
Estuvo vivo unos segundos. Es la forma de leer cualquier cosa de `settings.php` en el servidor
sin despertar al dueño para pedirle la contraseña.

### 2026-08-13 — El ERP corre en Drupal 11.4.5, y los ochenta y dos avisos de seguridad se quedan en cero

El sitio empezó el día en **Drupal 10.2.4 con 82 avisos de seguridad**. Pasó por la 10.6.15, que
los dejó en 2, y terminó en la **11.4.5 con ninguno**. Dos años y medio de parches sin aplicar,
puestos al día.

**El orden importó.** No se saltó del 10 al 11 de golpe. Primero se subieron los módulos
contribuidos uno a uno sobre Drupal 10, en tandas, comprobando después de cada una. Cuando llegó
el momento del núcleo, solo quedaban dos extensiones que rechazaran la 11, y las dos eran del
tema de administración. Eso convirtió el salto grande en un trámite.

**La herramienta que lo hizo posible.** A mitad de camino se escribió
`scripts/quien-bloquea-la-11.php`, que hace extensión por extensión la misma pregunta que hará
el núcleo al arrancar: ¿tu `core_version_requirement` acepta la 11.4? No pregunta a drupal.org,
no interpreta informes: reproduce la comprobación real. Dio tres bloqueos exactos donde antes
había una nube de sesenta módulos "por mirar". De los tres, uno se resolvió actualizando
(`feeds_tamper` a la `2.0.0-rc1`) y los otros dos tenían que ir con el núcleo.

**Lo que costó más de lo previsto fueron tres cosas que no estaban en el plan:**

El paquete `drupal/color` seguía exigido en `composer.json` aunque el módulo lo había
desinstalado `dxpr_theme` dos pasos antes. Pide `^9.4 || ^10`, así que bloqueaba la subida él
solo, y no aparecía en ninguna lista de módulos porque ya no era un módulo: era solo una línea
en un fichero.

Los parches de etiqueta no bastaban. Para `editablefields`, `simple_popup_views` y
`pdf_serialization` se habían escrito parches que amplían su `core_version_requirement` a
`^10 || ^11`. Eso es lo que lee **Drupal** al arrancar, y funciona. Pero **Composer** no lee el
`.info.yml`, lee el `composer.json` del paquete, y ahí seguían pidiendo la 10. Los parches se
aplican *después* de resolver dependencias, así que llegan tarde por definición. La solución es
`mglaman/composer-drupal-lenient`, un complemento que existe justo para esto: se le da una lista
de paquetes y relaja su restricción de núcleo al resolver.

Treinta y ocho paquetes de `vendor` estaban instalados desde git en vez de desde archivo.
`core-vendor-hardening` les borra los tests y la documentación, así que git los veía sucios y
Composer se negaba a tocarlos: *"Source directory has uncommitted changes"*. Se rehizo `vendor`
entero desde archivo. Tardó seis minutos y se llevó por delante toda esa clase de problema.

**Lo que entró con el núcleo:** Gin 5.0.15 y `gin_toolbar` 3.0.3, que piden `^11.2` y por eso no
se podían poner antes. Drush 13.7.6, porque el 12 no vale para la 11. Symfony 7.

**Un fallo de seguridad apagado de camino.** `rebuild_access` estaba en `TRUE` en `settings.php`
desde hacía mucho. Eso permite entrar a `/core/rebuild.php` **sin ninguna credencial** y vaciar
las cachés del sitio entero. En local es menor; en el servidor no lo sería. Queda comentado y
explicado.

**Una actualización que se reintentaba en bucle.** `dxpr_theme_helper` tiene una actualización,
la 8003, que hace dos cosas: encender `media_library_form_element` y migrar dos imágenes de
fondo del tema a entidades de medios. La segunda parte pide un servicio que no existe hasta
Drupal 11.3, así que reventaba, dejaba la versión de esquema en 8002 y volvía a intentarlo en
cada actualización. Se cerró a mano tras comprobar que las dos rutas de origen estaban vacías:
no había ninguna imagen que migrar. El script (`scripts/cerrar-8003.php`) lo vuelve a comprobar
y se niega a cerrar nada si alguna tuviera valor.

**Una herramienta que mentía, corregida.** Un script propio dijo que `feeds_tamper` no tenía
ninguna transformación configurada, y estuvo a punto de desinstalarse. Estaba mal: miraba en la
raíz de la configuración del tipo de importación, y Feeds Tamper las guarda bajo
`third_party_settings`. Sí había una, y es la que construye el título de las líneas de BoM
al importar. Se corrigió el script antes de seguir, porque una herramienta de diagnóstico que da
un falso negativo es peor que no tener ninguna.

**La copia de seguridad, por fin hecha herramienta.** Hasta hoy se hacía a mano cada vez.
`scripts/copia-de-seguridad.ps1` guarda las cinco piezas —base de datos, árbol del proyecto,
núcleo y `vendor`, ficheros subidos, e historia de git— y **comprueba al terminar** que las
cinco existen y pesan lo que deberían. Esa comprobación ya sirvió: el primer intento creó solo
la base de datos porque el `tar` de Git for Windows lee `C:\algo` como si `C` fuese un servidor
remoto, y el script lo cazó en vez de dejar una copia inútil con buena pinta. La historia de git
va aparte y se guarda aunque haya un GitHub detrás, porque hay 27 commits sin subir y mientras
tanto el único sitio donde existen es este disco.

**Comprobado en la 11:** 35 de 35 en la comprobación funcional, 33 páginas cargan sin un solo
fallo, los trece parches puestos, ninguna actualización de base pendiente, cero avisos de
seguridad. La configuración exportada se puso al día: 41 elementos actualizados y 2 borrados que
la 11 ya no usa. De los tres requisitos en rojo quedan dos, y ninguno es de la subida: el HTTPS,
normal en local, y el descuadre de `tec_gui`, que ya venía de antes y está en Deuda técnica.

Un detalle curioso: los tres falsos errores de ECA que ensuciaban el registro en cada
comprobación —los *"Running: TEC Inventory: Calculation data"* que aparecían con nivel de
emergencia— **han desaparecido** en la 11.

### 2026-08-13 — Paso 1 hacia Drupal 11: ECA salta de la rama 1 a la 2 sin despeinarse, y de paso cae el último aviso de seguridad

Era el paso que daba miedo: 36 procesos automáticos, 29 encendidos, todo el ERP colgando de ellos.
Salió mejor de lo previsto.

**Lo que se movió:** `eca` y sus seis submódulos de 1.1.13 a **2.1.22**, `bpmn_io` de 1.1.4 a
2.0.12, `eca_flag` de 1.0.0 a 2.0.5 y `eca_tamper` de 1.0.6 a 2.0.10. Todo sobre el Drupal 10.6.15
que ya corríamos, porque ECA 2 admite `^10.3 || ^11`. Composer no arrastró nada más: cinco
paquetes, cero instalaciones, cero bajas.

**Por qué la ruptura de ECA 2 no nos tocó.** ECA 2 rompe la API para quien la extienda desde
código: los suscriptores de eventos desaparecen y `$tokenServices` pasa a llamarse
`$tokenService`. Antes de tocar nada se revisaron nuestros módulos: ninguno extiende ECA. El único
suscriptor de eventos que tenemos, `QueueTileRedirectSubscriber`, es de Symfony y redirige las
baldosas de producción. Los 36 modelos son configuración pura.

**Y la migración de los modelos no cambió ni una coma.** Se guardó una foto de los 73 objetos de
configuración de ECA antes y otra después (`scripts/foto-eca.php`). Diferencia total entre las dos:
**una línea**, el ajuste nuevo `service_user: ''` que ECA 2 añade vacío. Los 36 modelos, idénticos
byte a byte. `drush eca:update` lo confirma por su cuenta: "36 modelos no necesitan cambios, 0
errores".

**El aviso de seguridad de Drupal, cerrado.** ECA 2.1.22 tapa `SA-CONTRIB-2026-074`, que era el
último que quedaba. `composer audit` ahora solo señala `psy/psysh`, la consola interactiva que trae
Drush: es una herramienta de línea de comandos, no está expuesta a la web y el fallo requiere
ejecutar `drush php` dentro de un directorio con un fichero preparado. De 82 avisos en febrero a
ninguno que afecte al sitio.

**El atasco de Quick Tabs, que no era de ECA.** Al ir a ejecutar las actualizaciones apareció esto:
*"la versión instalada del módulo Quick Tabs es demasiado antigua para actualizar"*. Herencia del
salto del núcleo de esta mañana, que subió `quicktabs` de la rama 3 de desarrollo a la 4.3.1 y dejó
dos cosas a medias. Bloqueaba **cualquier** actualización de **cualquier** módulo, así que había que
resolverlo sí o sí:

1. La versión de esquema se quedó en 8000 mientras la 4.3.1 declara haber borrado todo hasta la
   103001. La única actualización borrada instalaba el módulo `js_cookie`, que la rama 4 ya no usa
   porque la memoria de pestañas se guarda ahora en el navegador. `js_cookie` no está instalado,
   que es justo el estado que la 4 espera, así que no faltaba trabajo: faltaba el número.
2. La actualización posterior que añade el ajuste `remember_last_clicked_tab` **estaba anotada como
   ejecutada sin haberse aplicado**: las cinco instancias seguían sin la clave. Que no fue una
   anotación en bloque se sabe porque la *otra* actualización posterior de quicktabs, la de
   `direct_linking`, sí dejó su marca en la configuración. Se desanotó y se dejó que el código del
   propio módulo hiciera el trabajo, en vez de rellenar la configuración a mano.

Vale la pena quedarse con esto: **una actualización puede constar como hecha sin haberse hecho.**
Es un modo de fallo silencioso que no da error por ningún lado. Merece una revisión de las demás.

**Herramientas nuevas, que hacían falta para esto y harán falta para los siete pasos que quedan:**

- `scripts/cargan-las-paginas.php` — pide por dentro las 31 páginas que importan (producción,
  vistas, y una ficha real de cada tipo de entidad, que se busca sola por la API) y comprueba que
  responden. Hacía falta porque `comprobacion.php` mira datos y automatismos pero no dibuja ni una
  pantalla, y dibujar es donde asoman las subidas malas: el fallo de `views_aggregator` de esta
  mañana no rompió ni un dato, solo devolvía un 500 en la pantalla de pedidos.
- `scripts/exportar-config.php` — exporta a `config/sync` solo los objetos que se le pidan. Hace
  falta porque ahora mismo `drush config:export` a secas metería `update` y `upgrade_status`, que
  están encendidos solo para diagnosticar la subida, en `core.extension` y de ahí al servidor.
- `scripts/foto-eca.php`, `scripts/rutas-de-las-vistas.php`, `scripts/revisar-quicktabs.php` y
  `scripts/arreglar-quicktabs.php`.

**Comprobado al terminar:** 35 de 35 en la comprobación funcional (los seis automatismos siguen
disparando: título de BoM, copia de cantidad, enganche a la talla, movimiento de inventario,
título de color y recálculo del total de línea), 31 de 31 páginas cargando, y `config/sync` al día
con los seis objetos que cambiaron.

### 2026-08-13 — La otra mitad de la pregunta: hay versión para la 11 de todo menos de dos módulos

El informe de `upgrade_status` dice si el código que tenemos usa cosas que Drupal 11 haya quitado.
No dice si existe una versión publicada a la que actualizar, que es la mitad que decide el
calendario. `scripts/hay-version-para-11.php` la responde: consulta el historial de versiones de
drupal.org para cada uno de los 99 paquetes del proyecto y cruza la compatibilidad declarada.

**El resultado, y es mejor de lo que cabía esperar:**

| | paquetes |
|---|---|
| Ya están en una versión que admite la 11 | 37 |
| Hay versión que admite la 10 **y** la 11 a la vez | 60 |
| Solo hay versión que exige la 11 | **0** |
| No hay ninguna versión para la 11 | 2 |

Ese cero es el dato importante. Significa que **todo el proyecto se puede actualizar sobre Drupal
10, módulo a módulo, con el ERP funcionando entre paso y paso.** No hay ni un solo paquete que
obligue a moverse a la vez que el núcleo. El salto de la 11 queda reducido a mover el núcleo y
nada más, que es la única forma de que sea depurable si algo falla.

**Los dos que no tienen versión.** Los dos salían limpios de código en el informe de
`upgrade_status` —están en el grupo de "solo les falta declarar la 11 en su `.info.yml`"—, así que
el bloqueo es la etiqueta, no el código:

- **`simple_popup_views` 8.x-1.2**, de noviembre de 2022. El proyecto figura como "mantenido
  activamente" pero no publica nada desde entonces. Es el visor de fotos de ocho vistas:
  productos, inventario, líneas de pedido, patrones y los dos BoM. Molesta perderlo, pero
  no toca lógica de negocio.
- **`pdf_serialization` 2.2.0**, de septiembre de 2023, y este es peor: drupal.org lo marca como
  **`unsupported`**. Genera los PDF de las órdenes de compra. Ya le llevamos dos parches.

Para los dos, la salida barata es un parche que cambie el `core_version_requirement` y probarlo.
Para `pdf_serialization` hay además que decidir a medio plazo, porque un proyecto sin soporte no
recibe avisos de seguridad y eso ya nos costó dieciséis meses de exposición con ECA.

**Cuatro confirmaciones que cierran dudas del plan:**

- **ECA 2.1.22 declara `^10.3 || ^11`**, así que el salto de la rama 1 a la 2 —la parte peligrosa—
  se puede hacer sobre el Drupal 10 que corremos, sola y con la red puesta. Y de paso 2.1.22 es
  justamente la versión que cierra `SA-CONTRIB-2026-074`, el último aviso de seguridad que queda.
- **ECK 2.1.0 declara `^9.4 || ^10 || ^11`**. Igual: se hace antes del núcleo.
- **`field_permissions` deja de ser un bloqueo expreso.** La 8.x-1.5 admite la 11 y está cubierta
  por avisos. No hace falta el gancho de repuesto que habíamos pensado para `field_tec_cost`.
- **`dxpr_theme` 8.1.5 declara `^9.3 || ^10 || ^11`.** Esto cambia el plan del tema a mejor: en
  vez de parchear la dependencia de `core/modernizr` a mano, se actualiza el tema, y **también
  sobre Drupal 10**, así que la rotura visual se ve y se arregla antes del salto. Eso sí, es un
  salto de tres versiones mayores en el tema del sitio, así que de visual va a cambiar bastante.

**Y un aviso que va en contra de lo que conseguimos esta mañana.** Ocho módulos solo tienen
versión para la 11 fuera de la política de avisos de seguridad, y uno de ellos **solo tiene rama
de desarrollo**: `editablefields`. Los otros siete son `conditional_fields` (alpha6), `context`
(rc2), `feeds_tamper` (rc1), `filefield_paths` (rc1), `pwa` (beta7), `ultimate_cron` (beta1) y
`float_labels` (estable, pero el proyecto renuncia a la cobertura). Acabamos de sacar el proyecto
de las ramas de desarrollo precisamente porque nos escondieron un fallo crítico; subir a la 11
mete a `editablefields` de vuelta ahí. Hay que decidirlo a conciencia, no de pasada.

Dos paquetes no se pudieron consultar, `eca_ui` y `eca_modeller_bpmn`, y no es un problema: son
submódulos del proyecto `eca` y no tienen página propia en drupal.org.

### 2026-08-13 — El informe de compatibilidad con Drupal 11: cuatro módulos con trabajo real, y el tema deja de ser el bloqueante

Tres horas y media de análisis, 115 proyectos, 47.000 líneas de informe en bruto. El resultado
importa porque desmonta buena parte de lo que dábamos por hecho sobre lo que cuesta llegar a la
versión 11.

**El recuento, ya limpio:**

| | proyectos |
|---|---|
| Sin nada que tocar | 45 |
| Solo les falta declarar la 11 en su `.info.yml` | 60 |
| Avisos únicamente en sus propios tests | 6 |
| **Con código que hay que tocar de verdad** | **4** |

Los cuatro son ocho hallazgos en total: `flag` llama tres veces a `user_roles()`,
`views_entity_form_field` tres veces a `getEntityTranslation()`, `flood_control` una vez a
`user_role_names()`, y ECA usa una constante de clase marcada para desaparecer. Las cuatro se
arreglan actualizando el módulo, no escribiendo código.

**Lo nuestro sale intacto, y esta vez con el sitio en marcha.** `tec_production`, el módulo
grande, limpio del todo. `tec_crm_ux`, `tec_inventory`, `tec_brands`, `tec_crm` y
`admin_form_styles` solo necesitan cambiar su línea `core_version_requirement`. Esto ya lo decía
la auditoría de agosto, pero entonces se leyó el código a mano; ahora lo confirma un analizador
estático pasando por cada fichero.

**`dxpr_theme` ya no es el bloqueante que teníamos apuntado.** El punto 5 de la sección "Subir a
Drupal 11" decía que era "la parte cara" por dos razones, y ninguna de las dos se sostiene ya. La
primera, que declaraba necesitar `color`, un módulo que desapareció del núcleo: la dependencia
sigue ahí, pero desde la Fase 1 tenemos el `color` de contrib anclado en `composer.json`, y ese
sale limpio en el informe. La segunda, que su versión no admitiría la 11: la 5.2.1 que corremos
declara `core_version_requirement: '>=9.3'`, que la incluye. Sigue siendo un tema congelado desde
enero de 2024 y conviene actualizarlo, pero por mantenimiento, no porque cierre el paso.

Con una salvedad que despista al leer el informe: el paquete de `dxpr_theme` trae dentro un
`dxpr_theme_STARTERKIT`, una plantilla de ejemplo para hacerse un subtema, y **esa sí** declara
`^9.3 || ^10`. Sale marcada en el informe y parece un problema, pero no lo es: no está instalada.
Los temas activos son `dxpr_theme` (el del sitio), `gin` (el de administración), `bootstrap5`
(base del primero) y los tres del núcleo.

**Aviso sobre cómo leer estas cifras**, porque cuesta más de lo que parece. La herramienta reparte
sus hallazgos en tres cajones y solo uno importa:

- *Fix now* es código que Drupal 11 ya no tiene. Estos son los que cuentan.
- *Fix later* sigue funcionando en la 11 y desaparece en la 12.
- *Check manually* es casi todo ruido. Son 18.000 y pico avisos del tipo "llamada a método no
  definido" que salen de analizar los tests de cada módulo sin tener PHPUnit delante. No
  significan nada.

Y una trampa concreta que costó tres intentos: **el informe parte la ruta del fichero en varias
líneas cuando es larga**, dejando `FILE:` solo en la suya. Las rutas largas son justo las de
`tests/`, las más hondas. Leído a lo bruto, eso hace que los avisos de los tests parezcan estar en
el código del módulo: diez proyectos con trabajo pendiente en vez de cuatro. Queda resuelto en
`scripts/resumir-informe.php`, que es lo que hay que usar para leer este informe y los que vengan.

**Lo que el informe no dice**, y hay que tener presente: solo mira el código que tenemos. No dice
si existe una versión compatible con la 11 publicada en drupal.org para cada módulo, que es la
otra mitad de la pregunta. Eso se comprueba módulo a módulo cuando toque la Fase 4.

### 2026-08-13 — La prueba del clon destapa un fallo crítico de seguridad que llevaba dieciséis meses escondido

La prueba que nunca se había hecho: clonar el repositorio en una carpeta aparte, lanzar
`composer install` desde cero y comparar el resultado con el sitio real. Sirve para saber si lo
que hay en Git basta para levantar el ERP, que es exactamente lo que hace el servidor al
desplegar.

**Lo primero, la buena noticia.** Los **siete grupos de parches se aplicaron solos**, sin tocar
nada. Los tres rescatados esa misma mañana incluidos. La red que montamos funciona.

**Lo segundo, el fallo.** Comparando las dos copias aparecieron 44 ficheros distintos, todos en
módulos que colgaban de una rama de desarrollo en vez de una versión publicada. La causa: para
esos paquetes el `lock` apuntaba a un commit y en el disco había otro más nuevo que nadie
apuntó. En ECA, que es el motor de los 36 automatismos del ERP, la diferencia eran **catorce
meses**: el disco tenía la 1.1.7 y el `lock` un commit de febrero de 2024. Un `composer install`
en el servidor habría retrasado el motor del ERP sin avisar.

El inventario decía "cero descuadres" y no mentía. Los paquetes de rama no llevan número de
versión en su `.info.yml`, así que era el único punto ciego que le quedaba. Se cubrió con dos
guiones nuevos: `scripts/version-de-rama.php`, que compara la carpeta contra cada versión
publicada, y `scripts/commit-de-rama.php`, que recorre el historial hasta dar con el commit
exacto. Los dos usan el repositorio Git que Composer deja al clonar, así que no hace falta
descargar nada.

**Lo tercero, y es lo importante de verdad.** Al ponerle a ECA su número de versión real,
Composer pudo por fin cruzarlo con la lista de avisos de seguridad, y saltó uno que hasta
entonces era invisible: **`SA-CONTRIB-2025-031`, falsificación de peticiones entre sitios,
calificado de crítico**, de abril de 2025. Afecta a todo lo anterior a la 1.1.12. Corríamos la
1.1.7. Y la vía del ataque es el submódulo `eca_ui`, que en este sitio **está activado**.

Llevábamos dieciséis meses expuestos y no aparecía en ningún informe, precisamente porque el
módulo no tenía versión. Ese es el argumento de fondo contra las ramas de desarrollo, y aquí
está medido: no es que sean inestables, es que **te dejan fuera del sistema de avisos**.

Se subió ECA de la 1.1.7 a la **1.1.13**, última de la rama: once commits, 16 ficheros, todo
arreglos dentro de la misma rama menor. Además del fallo de seguridad se lleva por delante dos
problemas de cron que estaban sin diagnosticar aquí ("Multiple cron events not firing
consistently" y un error del evento de cron), un fallo fatal al importar configuración y varias
correcciones de acciones de lista. La pantalla de ECA carga con sus 36 procesos y las 35
comprobaciones pasan.

**Cómo quedan las siete ramas.** Cinco pasan a versión publicada, con el código idéntico al que
ya corría —lo único que cambia es que ahora traen su `LICENSE.txt`, que drupal.org añade al
empaquetar—: ECA a la 1.1.13, `bpmn_io` a la 1.1.4, `bootstrap_layout_builder` a la 2.1.2 y
`mimemail` a la 1.0.0-alpha6. Las otras dos, `eck` y `conditional_fields`, están a medio camino
entre dos versiones publicadas, así que se anclan a su commit exacto; igual que `ultimate_cron`,
donde además el `lock` estaba desfasado. Anclar el commit no da avisos de seguridad, pero sí
garantiza que una instalación desde cero reproduzca lo que corre hoy.

Efecto secundario agradecido: de siete repositorios Git anidados que dejaba la instalación
limpia se pasa a uno. Los paquetes con versión se bajan empaquetados; solo los anclados por
commit se clonan.

**Dos cosas menores que salieron por el camino.** `feeds_tamper` y `editablefields` estaban en
disco como copias de repositorio en vez de versiones empaquetadas; el código era el mismo salvo
tres métodos de `editablefields` que no llama nadie, comprobado en todos los módulos y temas del
proyecto antes de quitarlos. Los dos se reinstalaron empaquetados. Y `drupal/address`, que sí
tenía versión fijada, arrastraba un clon de 0,7 MB de una instalación vieja; reinstalado, ya es
un paquete normal.

**Cómo quedó la prueba.** Se repitió tres veces, clonando y reinstalando desde cero cada vez.
La primera dio 44 ficheros distintos, la segunda 5 y la tercera **ninguno**: el repositorio
reproduce el ERP exactamente. Repositorios Git anidados, de 7 a 3, y los tres que quedan son los
anclados por commit a propósito. Y una cosa que conviene tener presente para el servidor: en la
primera pasada Composer se plantó al no encontrar el `.git` de módulos que creía instalados
desde repositorio. La salida es borrar esas carpetas y dejar que las ponga de nuevo; los
ficheros están todos en Git, así que no se pierde nada.

### 2026-08-13 — Disco y `composer.lock` por fin dicen lo mismo, y aparecen tres parches que nadie había apuntado

Los veinticuatro descuadres entre lo que hay en disco y lo que Composer cree que hay resultaron
ser **veintiocho**, y están todos resueltos. Pero lo importante de esta sesión no es esa cuenta:
es lo que se encontró al ir a arreglarla.

**El hallazgo: tres parches que solo existían en la carpeta del módulo.** Antes de dejar que
Composer reinstalara veintiocho módulos había que asegurarse de que ninguno estaba retocado a
mano, porque un módulo reinstalado vuelve a su estado de fábrica y se lleva por delante
cualquier cambio no registrado. Ya nos pasó con `verf` en la Fase 2, y allí lo descubrimos
*después*. Para no repetirlo se escribió `scripts/comparar-con-original.php`, que descarga de
drupal.org el paquete oficial de cada módulo y lo compara fichero a fichero con el del disco.

De 112 proyectos, **90 estaban intactos y 9 tocados**. Seis eran cosas conocidas o inofensivas.
Los otros tres eran cambios reales que se habrían perdido sin decir nada:

- **`tamper`** multiplicaba sin convertir a número: `$data * $value` con dos textos. El ERP usa
  ese plugin a través de `eca_tamper` para calcular totales de línea, así que una multiplicación
  mal hecha acaba en un pedido de venta. Alguien lo arregló a mano y dejó un fichero `.backup` al
  lado. Ahora es `patches/tamper-math-multiplication-cast-to-float.patch`.
- **`select2`** tenía aplicado el parche del issue #3279387, que evita un error de JavaScript
  cuando la configuración del desplegable llega vacía. El fichero `.patch` estaba tirado dentro
  de la carpeta del módulo, sin registrar en ningún sitio.
- **`views_entity_form_field`**, que es la tabla editable de líneas de pedido, era la versión
  8.x-1.0 con el parche de AJAX del issue #2998721 encima. **Este era el grave**: sin ese parche
  no se puede guardar una línea sin recargar la página entera. El parche original de drupal.org
  ya no encaja con la 8.x-1.0 publicada, así que se generó uno nuevo a partir de la diferencia
  real y se comprobó que reproduce el disco byte a byte.

Los tres están ahora en `patches/` y registrados en `composer.json`. Composer los aplica solo,
y con `composer-exit-on-patch-failure` activo desde la Fase 0, si alguno dejara de encajar la
instalación se para en voz alta en vez de seguir en silencio.

**El aviso de seguridad de ECA no nos afecta.** Era el último que quedaba de los 82, y su
solución oficial es saltar a ECA 2, que es la Fase 3 entera. Pero la letra pequeña del aviso
dice que solo es explotable si el sitio usa la acción "Render: Twig" del submódulo `eca_render`.
Ese submódulo **está desinstalado**, y ninguno de los 36 procesos lo menciona. El código
vulnerable está en el disco pero Drupal no ejecuta módulos apagados. Conclusión: ECA 2 sigue
siendo deseable, pero ya no es urgente ni es una decisión de seguridad.

**La reconciliación.** Se hizo en dos rondas a propósito, para poder saber cuál había sido si
algo se rompía: primero 23 módulos de salto pequeño, después los 5 con salto de verdad (`ds` de
3.19 a 3.37, `feeds` de una beta a la 3.2.0 estable, `smtp`, `views_bootstrap` y `gin_toolbar`).
Las 35 comprobaciones pasaron después de cada ronda. Diez ganchos de actualización tocaron ocho
vistas y dos ajustes; se revisó uno a uno lo que cambiaban —renombrar opciones y convertir tipos,
nada perdido— y se llevaron a `config/sync`. **Ahora el inventario da 0 descuadres.**

**Los dos módulos que Composer no sabía que existían.** `quicktabs` y `views_entity_form_field`
estaban en disco y activos pero fuera de `composer.json`, así que una instalación desde cero
simplemente no los ponía. `views_entity_form_field` resultó ser la 8.x-1.0 parcheada, ya
contada arriba. `quicktabs` era una copia del repositorio de la rama 3, no de una versión
publicada: se notaba porque le faltaba el `LICENSE.txt` y le sobraban los ficheros de
integración continua. Se intentó anclarlo a esa rama y Composer lo instaló **clonando**, con
lo que dejaba un repositorio Git anidado de 1 MB dentro del proyecto y los 59 ficheros con
finales de línea de Windows. Eso es exactamente el problema que costó una tarde al montar
GitHub el 12 de agosto, así que se descartó y se subió a la **4.3.1**, que es versión publicada,
soporta Drupal 11 y migró las cinco instancias de pestañas con dos ganchos que dicen
expresamente que dejan el comportamiento anterior intacto. Comprobado a ojo: las tres pestañas
de la ficha de CRM (compras, inventario, detalles) se siguen dibujando.

**Lo que se miró por encargo.** `ief_table_view_mode`, que dibuja la tabla de líneas del pedido,
tenía en disco una instantánea de rama de desarrollo de 2023 donde el `lock` pedía la 3.0.0. Se
comparó con la 3.0.1 publicada y **solo cambia la metadata**: ni una línea de código. Se subió a
la 3.0.1 sin riesgo. De paso salió una cosa que no es un fallo pero conviene saber: esa tabla
sale con las columnas genéricas de Inline Entity Form (Título, Tipo, Operaciones) porque el
formulario **no tiene modo de vista configurado**. Es así desde antes de tocar nada —la
configuración en Git es idéntica— pero significa que el módulo no está haciendo lo único que
sabe hacer. Merece una mirada cuando haya tiempo.

**Cómo queda.** `composer validate` dice *valid* por primera vez: `composer.json` y
`composer.lock` cuadran. Cero descuadres entre disco y `lock`. 14 páginas del ERP responden 200,
incluidas las de pedidos, cola de producción, control de existencias y CRM. 35 de 35
comprobaciones.

**Un tropiezo del que aprender.** A mitad de faena se lanzó un `composer require --dry-run` para
ver qué proponía. Ese comando **escribe en `composer.json` aunque sea un simulacro**, así que se
deshizo con `git checkout composer.json`, y eso se llevó por delante hora y media de ediciones
—los parches registrados y las siete restricciones ajustadas— mientras el `lock` y el disco ya
tenían los cambios nuevos. Hubo que rehacerlas. La lección: `--dry-run` de `require` no es de
solo lectura, y `git checkout` sobre un fichero con trabajo sin confirmar no perdona.

### 2026-08-13 — Fase 2: el ERP corre en Drupal 10.6.15, la página de pedidos vuelve a abrir y quedan 2 alertas de seguridad de 82

Dos años y medio de parches sin aplicar, puestos en una noche. **El núcleo pasa de 10.2.4 a
10.6.15**, la última de la rama 10, que es la puerta obligatoria para llegar algún día a Drupal
11. Las 35 comprobaciones del ERP pasan, las páginas que se probaron responden y no ha hecho
falta tocar nada del contenido.

**Cómo se hizo, y por qué así.** El ensayo de una actualización completa resolvía sin
conflictos, pero eran 107 cambios a la vez. Se hizo la versión acotada: subir solo el núcleo y
lo que arrastra. Resultado, **27 actualizaciones en vez de 107, y de módulos contribuidos se
movió uno solo**, `entity_browser` (2.10 → 2.17), porque el suyo no servía para el núcleo nuevo.
ECA, ECK, los temas y todo lo demás se quedaron donde estaban, que era justo la intención.

De paso Composer **borró del disco los 48 módulos muertos**: las carpetas de `modules/contrib`
bajaron de 157 a 109. Ahí se fue el código de la IA que llevaba desde el 12 de agosto esperando
a poder quitarse, más Commerce entero, `search_api`, `shield`, `quicklink` y `memcache`.

**Las alertas de seguridad, de 82 en 10 paquetes a 2 en 2.** Las que quedan son `eca` —de
gravedad baja, se va con el salto a ECA 2— y `psy/psysh`, que es una dependencia de Drush y solo
afecta en local. Por el camino se actualizó también `mail_login` de la 3.0.0 a la 4.2.2, que
tenía un aviso **crítico de salto de control de acceso** (SA-CONTRIB-2025-088).

**La página de pedidos ya abre**, y no fue como yo había anticipado. Dije que subir el núcleo la
arreglaría sola porque `views_aggregator` pedía 10.3; me equivoqué. Eso era la declaración de
compatibilidad del módulo, no la causa del error 500. El fallo era suyo: `getCellRaw()` promete
devolver texto, pero varias de sus ramas pueden devolver `false` —`reset()` sobre un array
vacío, un campo booleano— y eso tumba la página entera con un `TypeError`. Está arreglado con un
parche nuestro, registrado en Composer, que devuelve celda vacía en esos casos. `/tec_order/348`
pasa de 500 a 200 con 352 KB.

**Cuatro tropiezos que conviene tener anotados**, porque los tres primeros volverán a pasar en
el servidor.

1. **Composer se plantó a mitad.** `vendor/symfony/css-selector` estaba instalado desde git y
   tenía "cambios sin confirmar", así que Composer se negó a borrarlo. No era corrupción: es el
   propio plugin `core-vendor-hardening` de Drupal, que elimina las carpetas `Tests/` de
   `vendor` por seguridad, y Git las ve como borrados pendientes. Hay **43 paquetes instalados
   desde git y 18 de ellos están así**. Se resolvió borrando a mano las tres carpetas que
   Composer necesitaba mover y relanzando. Si vuelve a pasar, es lo mismo.
2. **`drush updatedb` no funciona en este equipo.** Drush se relanza a sí mismo en un
   subproceso con la ruta en barras normales, y `cmd.exe` no la reconoce. Además no existe
   `vendor\bin\drush.bat`. Se hizo un script propio, `scripts/actualizar-bd.php`, que llama a
   la misma interfaz de Drupal sin subprocesos.
3. **Y ese script tenía un fallo silencioso muy feo.** Las cuatro actualizaciones se ejecutaban
   pero **la versión del esquema no se guardaba**, así que volvían a salir como pendientes una y
   otra vez. La causa está en `core/includes/update.inc` línea 213: solo apunta la versión si
   `$context['finished']` vale 1, y esa clave únicamente se rellena sola cuando la actualización
   trabaja por lotes. Hay que inicializarla a mano. Ya está corregido y verificado:
   `system` 10201 → 10600, `entity_browser` 8201 → 8202, `locale` 10100 → 10300.
4. **El andamiaje del núcleo se llevó por delante `.gitattributes`**, y con él la línea
   `*.patch text eol=lf` que se había puesto esa misma noche en la Fase 0. O sea que el arreglo
   duró unas horas. Ahora está resuelto de verdad: la línea vive en
   `scaffold/gitattributes-append.txt` y `composer.json` le dice al andamiaje que la **añada**
   en cada instalación en vez de sobrescribir el fichero. Verificado relanzando el andamiaje.

**Dos regresiones que se detectaron a tiempo, y lo que enseñan.** Al reinstalar los módulos que
Composer no conocía, escribió las versiones publicadas encima de código que estaba por delante.
`file_uploader` perdió el arreglo de los validadores de ficheros de Drupal 10.2 y `verf` perdió
tres parches. Se vieron comparando con Git antes de confirmar.

- `file_uploader` se dejó en la **1.1.0**, porque esa versión ya trae el arreglo de arriba. Lo
  que había en el disco era un parcheo manual que además **había fallado a medias**: quedaban un
  `.orig` y un `.rej` en la carpeta, que son los restos de una aplicación rechazada.
- `verf` se devolvió a la **2.0.2** con sus tres parches ahora registrados en Composer. El que
  importaba de verdad hace que el desplegable del filtro ofrezca **solo los valores que están en
  uso**, con una consulta directa, en vez de cargar todas las entidades. Con 869 materiales en el
  ERP eso se nota. El código quedó byte a byte como estaba.
- `views_aggregator` tenía otro apaño manual, un puente para `renderInIsolation()` que no existía
  en Drupal 10.2. Composer lo borró y esta vez está bien: en 10.6 ese método ya existe.

La lección es la de siempre en este proyecto, y ahora con tres casos más: **cada módulo tocado a
mano es una bomba de relojería** hasta que su cambio esté en un fichero de `patches/` y
declarado en `composer.json`. Con los de hoy quedan seis parches registrados y aplicándose
solos. Siguen fuera `quicktabs` y `views_entity_form_field`.

**Y la Fase 1 se cerró de rebote.** El paso 11, sincronizar el `lock`, estaba bloqueado
precisamente por `views_aggregator`. Al subir el núcleo se desbloqueó, y `composer validate`
ya no da ni un aviso: **`composer.json` y `composer.lock` cuadran por primera vez**.

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
y cero diferencias** en BoM, líneas, pedidos, materiales, usuarios y procesos de ECA. Las
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

- Un artículo de BoM nuevo **se pone el título solo** a partir del material.
- Al modificarlo, **copia la cantidad** al campo de entrada.
- Si lleva talla, **se engancha solo a la variación de talla** (la talla pasó de 24 a 25).
- Un movimiento de inventario **se engancha solo al material** (pasó de 2 a 3 movimientos).
- Una variación de color **se pone el título sola** a partir del color.
- Una línea de pedido **recalcula su total**: 1.200 × 2 dio 2.400.

Todo lo creado durante la prueba se borró después, y la comprobación final lo confirma.

**La prueba de que no se ha perdido nada.** Se cargó la copia de las 02:58 —anterior a que se
tocara el primer módulo— en una base de datos aparte y se compararon los recuentos de todas las
tablas de contenido contra las de ahora: **cero diferencias**. Los 4.773 artículos de
BoM, los 178 elementos de BoM de línea, las 85 líneas de venta, los 869
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

La causa: la línea 1567 tiene 24 elementos de BoM, y **cuatro de ellos apuntan a
materiales que ya no existen** (los términos 2321, 2456 y 2524). El proceso recorre el
BoM, intenta cargar el material fantasma, no lo encuentra y muere ahí.

Y el alcance es mayor de lo que parecía: **2.439 de los 4.773 artículos de BoM apuntan a
materiales borrados**, 88 materiales fantasma en total, más 12 movimientos de inventario y 8
elementos de BoM de línea. Alguien borró esos 88 materiales en su día sin limpiar las
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
  de BoM) y `process_uhiwdqa` (importar artículos de BoM), la acción de IA seguía
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
