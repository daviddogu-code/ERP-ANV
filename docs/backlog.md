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
  queda idéntico a lo que se probó. Lo único que se pierde es la cuenta de Lukpla, que se vuelve
  a crear en dos minutos. Aun así, antes hay que pasar `drush config:status` **en el servidor** y
  contar los procesos de ECA, que deben ser 36.

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

  Sobre ese fallo: el servidor sigue con **ECA 1.1.7 y `eca_ui` activado**, o sea **expuesto al
  CSRF crítico de `SA-CONTRIB-2025-031`**. Aquí ya no, porque ECA subió a la 2.1.22, pero allí sí,
  y allí es donde está la máquina abierta a internet. Es el motivo más fuerte para que el
  despliegue no espere: de todo lo que hay en este backlog, es lo único que empeora solo con el
  paso del tiempo.
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

~~**Todo el contenido que hay dentro es de prueba y se borra entero.**~~ **Borrado el 14 de
agosto de 2026.** El detalle está abajo, en Hecho: 7.003 fichas y 892 términos fuera en
sesenta y siete segundos, y el ERP en verde después. El vocabulario de tipos de contacto se
respetó, que era el aviso que podía costar caro.

Del orden en que se hizo y de por qué, en Hecho. Aquí queda solo lo que sigue pendiente.

**El importador de materiales es ahora lo único que separa al ERP de tener catálogo.** Antes era
una mejora; desde el borrado es la puerta de entrada, porque no hay otra manera de meter 869
materiales. Existe (`tec_inventory_csv_importer`) y por él entraron 470 de los de prueba, pero
está a medio hacer:

- **Rellena 5 de los 56 campos** de un material: nombre, descripción, trazabilidad, unidad de
  uso y tipo de material. Precios, unidades de compra y de stock, factores de conversión,
  punto de pedido, plazo de entrega, imagen, SKU y embalaje entran vacíos. De ahí la
  sensación de que "la información no está bien": no es incorrecta, es que falta.
- **El proveedor no se enlaza nunca.** El importador sí lee una columna `Suppliers` del CSV,
  está declarada ahí dentro, pero no está conectada a ningún campo: lee el dato y lo tira. Lo
  mismo le ocurre a una columna de coste.
- ~~**Hay dos campos de proveedor y hay que elegir cuál es el bueno.**~~ **Resuelto el 14 de
  agosto, y lo resolvieron los datos.** Al sacar los materiales a CSV antes de borrarlos se
  contó cuántos traían cada campo relleno: `field_tec_vendor` lo tenían **38 materiales** y
  `field_tec_suppliers` **ninguno, cero de 869**. O sea que el campo que se usaba de verdad es
  `field_tec_vendor`, el obligatorio que se elige de una lista, y el otro se puede retirar. La
  decisión se llevaba semanas en el aire y se contestó sola en cuanto hubo con qué contar.
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

### Los tres cabos que dejó el borrado

- **Retirar Marcas y Patrones de la configuración.** Sus términos se borraron, pero los dos
  vocabularios siguen ahí vacíos, y con ellos el campo en productos, tres vistas
  (`tec_brands`, `tec_patterns`, `tec_pattern_elements`), los formularios, los permisos en tres
  roles y los campos propios de cada vocabulario.

  **No se puede borrar del tirón, y esto es el hallazgo.** Los dos procesos de duplicar
  producto —`process_llpx4tp` y `process_icpsbgv`, "TEC Product: Duplicate product" y su
  clon— copian `field_tec_brand` y `field_tec_pattern` al duplicar. Si se borran los campos sin
  desmontar antes esos dos procesos, **se rompe el duplicado de productos**, que es una función
  viva y de las que más se usan. Así que el orden es: primero quitar esos dos pasos de los dos
  procesos, y después los campos, las vistas y los vocabularios. Es un cambio de configuración,
  o sea que viaja por Git y se puede revisar antes de aplicar.

  Es la razón por la que el 14 de agosto se paró aquí: el borrado de datos y el desmontaje de
  configuración son dos trabajos distintos, y mezclarlos a las tres de la mañana era la manera
  de no saber después qué había roto qué.

- **Decidir qué se hace con 315 ficheros huérfanos.** Son sobre todo las imágenes de los
  productos y patrones que se borraron. Drupal no los borra solo: sin activar la opción de
  marcar como temporales los que nadie usa, se quedan en el disco para siempre y con su ficha
  en la base de datos. No molestan, pero engordan cada copia de seguridad y viajan al servidor
  en el despliegue. Antes de borrarlos hay que comprobar cuáles tienen de verdad cero usos, que
  algunos pueden estar colgando de los doce nodos o de los colores que se quedan.

- **Devolverle a la prueba de humo las seis pantallas que ha perdido.** Con la base vacía no hay
  con qué pedirlas, así que se salta `o/draft/%`, `po/draft/%`, `po/%/print`, `o/pf/%/print`,
  `po/%/x/pdf` y `tec_crm/%/reorder`. Pasó de mirar veintiséis pantallas a veinte, y las seis que
  faltan son las peores de perder: los dos editores de líneas —donde vivía el error 500 del 13
  de agosto—, las dos de imprimir y la del PDF.

  El 14 de agosto se hizo lo baratísimo, que era **que lo diga**: antes se las saltaba en
  silencio y el resumen ponía "todas cargan", que es la clase de frase con la que uno despliega
  tranquilo sin motivo. Ahora las nombra una por una. Lo que falta es lo bueno: **que la prueba
  se fabrique un pedido con líneas**, lo pida, y lo borre, igual que hace ya la comprobación del
  ERP desde esa misma noche. Mientras no lo haga, el despliegue se verifica con seis pantallas
  menos, y una de ellas es la que ya se rompió una vez.

  Ese mismo día se le añadió lo que le faltaba por el otro lado: **ahora pide las doce portadas
  del ERP**, que son nodos y no vistas, y por eso no las descubría. De 20 pantallas a 31. El
  motivo está abajo, en Hecho: por ese hueco se colaron diez botones de crear escondidos.

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

- **`tec_gui`: un tipo de entidad abandonado que deja el informe de estado en rojo.** Encontrado
  el 13 de agosto al subir a Drupal 11, al revisar los requisitos del sitio. Es un tipo de
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

- **Trece tablas `tmp_b44c2b*` en la base de datos.** Ocupan poco, 0,8 MB, y son copias de las
  tablas de `tec_gui`. Restos de alguna operación de copia o de Backup and Migrate que no
  limpió. Se pueden borrar, pero conviene hacerlo a la vez que se decida qué pasa con `tec_gui`.

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
- **Quince avisos de PHP por cada carga de la pantalla de líneas de pedido.**
  `views_simple_math_field` hace `$entity->get($campo)->getValue()[0]['value']` sin comprobar que
  el campo tenga algo, así que cada línea con un campo vacío deja dos avisos en el registro
  (`SimpleMathField.php:346`). No rompe nada, la página responde 200, pero ensucia el registro y
  ahí es donde se buscan los fallos de verdad. Salieron a la luz el 13 de agosto, cuando la prueba
  de humo empezó a ejecutar esa vista de verdad. Se arregla con un parche de una línea.

---

## Hecho

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
producción, escandallos de línea, escandallos, movimientos, líneas, pedidos, variaciones,
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

**La comprobación del ERP cambió de oficio.** Vigilaba recuentos —4.773 escandallos, 869
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
  escucha el evento de actualizar, y todo lo que calcula gira alrededor del escandallo de la
  talla. Una línea que no cuelga de una talla no es un caso real y el proceso la deja en paz. Con
  la talla puesta, cambiar la cantidad a 6 con precio 12,50 da **75,00** al primer intento.
- **Al guardar una línea, ECA crea por su cuenta un escandallo de línea y lo engancha.** Nadie lo
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
entera del inventario. Un selector propio de materiales para el escandallo —el plugin
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
escandallo— porque son **los pedidos 755 y 756 creados a mano anoche** para comprobar que la pantalla
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
`third_party_settings`. Sí había una, y es la que construye el título de las líneas de escandallo
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
disparando: título de escandallo, copia de cantidad, enganche a la talla, movimiento de inventario,
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
  productos, inventario, líneas de pedido, patrones y los dos escandallos. Molesta perderlo, pero
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
