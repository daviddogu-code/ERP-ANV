# Backlog — ERP ANV

> Registro vivo de tareas pendientes y de decisiones ya tomadas.
> Escrito en español porque el lector principal es el dueño del proyecto.
> Las otras notas de `docs/` están en inglés y son documentación técnica; esta no.
>
> Última actualización: 2026-09-05.

## Cómo usar este archivo

Se lee y se edita desde tres sitios: en Cursor (`docs/backlog.md`), en el Explorador
de Windows (`C:\laragon\www\tec\docs\backlog.md`) o desde el móvil en GitHub, en
`github.com/daviddogu-code/ERP-ANV`, carpeta `docs`.

**Está ordenado de más urgente a menos.** Lo de arriba es lo que hay que mirar hoy; lo de
abajo puede esperar meses sin que pase nada. Si abres el archivo agobiado, quédate con
**quién opera** y las dos primeras secciones, y cierra.

Cuando una tarea se termina, se mueve a la sección "Hecho" con la fecha y una línea
explicando por qué se hizo. Esa explicación es lo que evita rehacer trabajo o deshacer
por error algo que estaba puesto a propósito.

---

## Quién opera y adónde va el trabajo

Fijado el 28 de agosto de 2026. **El 31 de agosto arrancó el dual run:** Lukpla (con Noi)
usa el ERP **y** las Google Sheets a la vez. El ERP está publicado; las pantallas copian
esas hojas. Caduca el plan de agosto de «Lukpla solo revisa y luego entran tres empleados»
(secciones 1 y 3, tachadas).

### Quién es quién

- **David:** dueño. Ya no está en la fábrica a diario (hace ~3 meses). Va **una vez por
  semana**. Sigue siendo el hombre orquesta y está saturado; el destino es **CEO** y más
  adelante casi fuera, porque **opera, además de este, otros negocios**. Las Google Sheets las
  **diseñó él**, pero **no las usa cada día** y no recuerda toda la lógica. **Las ventas las
  hace todas él:** desde que encuentra al cliente (o el cliente le encuentra) **hasta que
  cierra**. Hoy eso es **sin SOPs ni protocolos, totalmente manual**. El supervisor **no
  vendió nunca**. El customer service por WhatsApp y email **le satura**.

- **Lukpla:** dueña del negocio (socia). Está en la fábrica todos los días. Es parte de la
  empresa; no se va. Opera otras partes del negocio que no están en el ERP y además hace todo
  el trabajo del supervisor que se fue, con la ayuda de un familiar. Ese familiar ayuda de
  forma temporal. Lukpla y su familiar, que se llama **Noi**, trabajan con las Google Sheets
  cada día. Lukpla es quien tiene que **testear el ERP**. Puede usar cualquier cuenta del ERP
  (`executive`, `manager`, `supervisor`, etc.). Si, cuando el ERP esté del todo desarrollado,
  siguen existiendo tareas de supervisor, se contratará a alguien para esas tareas y esa
  persona usará la cuenta `supervisor`. El rol Drupal de la cuenta `lukpla` (*tec_supervisor*)
  no es el puesto de supervisor que no se quiere volver a montar.

- **El supervisor que se fue:** 1,5 años. Entonces David **sí estaba** en la fábrica y lo
  formó a fondo. Lo trajo **de otro país** para bajar el riesgo de que usara la información
  confidencial para competir. Se fue igual: **pérdida de tiempo y de dinero**; formar a
  alguien así es largo y caro. **No hacía ventas** (ni buscar cliente ni cerrar). Recogía
  **las cantidades de los pedidos** —sobre todo **re-orders** de quien David ya había
  cerrado— y ejecutaba **los procesos que le correspondían** (proforma, BoM, stock, PO,
  recepción, packing, facturas, etc.). El puesto **tal como era** (un humano que unía esos
  procesos, con acceso a casi todo) **no se vuelve a montar**. Lo que quede de “supervisor”
  **después** del ERP, si queda, es un puesto **más estrecho**, con esa cuenta, no otra
  formación de 1,5 años sobre el negocio entero.

- **Planta (~55 personas):** ya empaquetan. El recuento de stock **ya no es solo de oficina**:
  hay un sistema para que **empleados de planta** cuenten e introduzcan datos en sheets (antes
  no podían por el **inglés / alfabeto**). En el ERP existe el **production log**.

### Qué hacía el supervisor

Nunca el cierre de una venta. Partía de cantidades de pedidos —re-orders— de clientes ya
cerrados por David.

- Recoger **las cantidades de los pedidos** (WhatsApp y email) y seguir los procesos de
  fábrica que de ahí salen
- **Proformas**, envío al cliente; **mockups** si había producto o color nuevo
- **BoMs:** calcular materiales de productos nuevos y de antiguos mal calculados
- Meter esas BoMs en **Google Sheets diseñadas por David**. Después las metía en **otra
  sheet** que cogía las cantidades de las **proformas** y sacaba el **cálculo de required
  material por proforma** (archivos incompletos y con **errores humanos** → por eso el ERP)
- Eso iba al **stock control** (la sheet con la que se dibujó `/stock`)
- De ahí: qué falta y **armar POs**
- Mandar POs a **grupos de LINE** (casi todos los proveedores locales; **muy pocos por
  email**) y preguntar **ETA**
- **Recibir** las materias primas, **comprobar que estuvieran bien**, **contarlas** y
  **meterlas en el stock**
- Contar stock
- Empaquetar y **packing list**
- **Facturas**

### Cómo se opera ahora, y el destino

- **Dual run, arrancado el 31 de agosto de 2026:** ERP **y** sheets **a la vez**. Se ve qué
  falla o falta, se pule. Cuando aguante, **se cierra Drive**.
- Lo que Lukpla ve lo manda en **un grupo de LINE**. David (o el agente) lo arregla ahí.
  Así se arregló el 31 de agosto el Download de las facturas. No hace falta una hoja de
  bugs: el chat de LINE es el canal, y el arreglo queda en git y en el backlog.
- **Sueño:** el máximo de ese trabajo en ERP + Lukpla + planta + agentes; David fuera de la
  operación. **Realista:** no es el 100 % de cada cosa; es un **% alto en muchos sitios**, y
  eso en conjunto es enorme.

**Ventas** (primer contacto → **cierre**): hoy **solo David**, a mano, sin protocolo. Hay que
**sacar esa tarea de su cabeza** y partirla en dos:

- **Captación:** web OEM con **SEO potente** (encargo en
  [`docs/web-anvfightgear.md`](web-anvfightgear.md)), para que el cliente llegue solo.
- **Sistematización:** **SOP de cierre de venta** (aún por decidir y redactar; es complejo).
  La máxima información posible para **cerrar la venta** y **sistematizar el negocio**:
  límites claros de lo que se hace y de lo que no, **MOQs**, **materias primas** que se
  usan, **colores**, etc. **No confundir** con el SOP ya escrito
  [`docs/sop-give-a-customer-a-portal-login.md`](sop-give-a-customer-a-portal-login.md)
  (*Give a customer a portal login*): ese es solo **abrir `/my`** cuando la empresa ya
  está en el CRM.

**Re-orders** (el cliente ya cerrado vuelve a pedir): recoger cantidades y el resto de
procesos → hoy Lukpla (+ Noi); destino **portal + ERP + agente**. Mientras los clientes no
usen el portal, **lo usáis vosotros como si fuerais ellos**. Luego se van pasando **clientes
viejos**. **Clientes nuevos, una vez cerrados: solo portal** para pedir.

### Qué puede hacer un agente de IA dentro del ERP

**Todo proceso que el ERP sepa hacer**, el agente lo puede ejecutar: crear y confirmar pedidos
y POs, generar y enviar proformas y PDFs, escribir ETAs, recibir y meter stock, packing list,
facturas, explosión de BoM, lista de compra, avisos. El techo no es “solo propone”: es **lo
que esté construido y con datos**. Lukpla (u otra persona) solo entra en lo que **no esté en
pantalla ni en el SOP** —cierre de venta, mockup, receta de un producto que aún no existe,
excepción que el sistema no contempla.

Para **actuar hacia fuera** hace falta un canal que el sistema pueda usar (email/SMTP, LINE
Official Account, WhatsApp Business, portal). Los grupos personales de LINE/WhatsApp no son
ese canal hasta que haya cuenta de empresa; mientras tanto ese tramo sigue siendo humano o no
se automatiza. No es una regla de “el agente no debe”: es que **no hay API** en el móvil de
alguien.

Sin el ERP en uso (hoy todo está en Sheets) el agente no tiene sobre qué operar. El orden es:
**mudar el negocio al ERP** → portal y SOPs → el agente pisa cada proceso que ya esté en el
sistema. El % que se come es el de esos procesos, no un chat genérico.

### Cómo testea Lukpla (David fuera del medio)

- Manda lo que ve en **un grupo de LINE**. David lo va arreglando. Así se hizo el
  Download de las facturas el 31 de agosto. No hay un chat de tres en Cursor ni una
  hoja de bugs: el grupo es el canal.
- Empezar por **una** hoja de las que ellas tocan cada día, no por las doce.

---

## 1. Ahora mismo

- ~~**Llevar al servidor el salto a Drupal 11.4.5.**~~ **Hecho el 20 de agosto de 2026.** Producción
  en Singapur (`erp-anv-sgp1`, `https://erp.anvfightgear.com`), Drupal **11.4.5**, comprobación
  **220/223**. El relato del despliegue y del cierre de Nueva York están en Hecho.

- ~~**Dar de baja el servidor de Nueva York.**~~ **Hecho el 28 de agosto de 2026.** Destruido el
  droplet `actafight.com` (NYC3, `174.138.49.12`). Sin IP reservada. El snapshot
  `actafight.com-1755603370919` también se borró ese día; no queda copia del disco viejo en
  DigitalOcean, solo las copias diarias de `erp-anv-sgp1` y las de Drive/Git.

<details>
<summary>Notas del despliegue a Singapur (agosto 2026, ya ejecutado)</summary>

- **Llevar al servidor el salto a Drupal 11.4.5.** Hecho en local el 13 de agosto; el servidor
  siguió en **10.2.4 con los 82 avisos de seguridad** hasta el despliegue del 20 de agosto.

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
  —`lukpla`, `coo` y `manager`—, así que viajan con la base. ~~Lo único que hay que hacer al llegar es
  ponerles la contraseña.~~ **Hecho el 28 de agosto de 2026** en producción.

  **Caducado el 28 de agosto de 2026 si hay operación en producción:** el día que Lukpla (o
  Noi) meta datos reales en el servidor, **ya no se pisa esa base con un volcado de local**.
  Local sigue sin ser el negocio; producción sí puede serlo. Ver [Quién opera](#quién-opera-y-adónde-va-el-trabajo).

  Aun así, antes hay que pasar `drush config:status` **en el servidor** y
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

</details>

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

- ~~**Lukpla testa el ERP en paralelo con las Google Sheets** (con Noi).~~ **Arrancado el 31
  de agosto de 2026.** Dual run hasta que el ERP aguante; entonces se cierra Drive. Lo que
  ve lo manda en un grupo de LINE; David lo arregla. Detalle en
  [Quién opera](#quién-opera-y-adónde-va-el-trabajo).

~~**La revisión de Lukpla queda aparcada** por decisión del dueño el 13 de agosto: se le dirá que
de momento no entre, para poder trabajar sin la restricción de no romperle nada.~~ **Caducado el
28 de agosto de 2026.** Ya no es una revisión aplazada: Lukpla entra a **usar y testear** (ERP +
sheets a la vez). El dual run arrancó el 31 de agosto. Lo que había preparado para cuando se retomara:

- ~~**Crear la cuenta de Lukpla.**~~ Hecha, con rol *supervisor*, directamente en el servidor. Y
  **desde el 15 de agosto existe también en local**, con las de `coo` y `manager`, así que deja de
  ser una cuenta que solo vive en un lado. El rol Drupal no es el puesto de supervisor que no se
  quiere volver a montar (ver [Quién opera](#quién-opera-y-adónde-va-el-trabajo)).
- ~~**Llevar al servidor la limpieza de módulos del 13 de agosto**~~ Cubierto por el despliegue del
  20 de agosto. Si se importa configuración desde local a un servidor que ya tenga datos de
  prueba de Lukpla, volver a pasar `drush config:status` antes de cualquier `cim`.
- ~~**Preparar dónde apunta lo que vea.**~~ **Grupo de LINE**, confirmado el 31 de agosto de
  2026. Ella manda lo que ve; David lo arregla. Así se hizo el Download de las facturas.
  Una hoja de cálculo no hace falta: el arreglo queda en git y en este backlog.
- **Darle un encargo concreto**, empezando por **una** sheet de las que tocan cada día, en vez
  de un "míralo a ver qué te parece".
- ~~**Confirmar si se maneja en inglés.**~~ **Sí**, confirmado el 31 de agosto de 2026. El dual
  run de Lukpla va en el inglés del ERP. El andamio tailandés no traduce el ERP; no hace falta
  para ella. El día que un operario de planta tenga usuario, eso es otro asunto (sección 3).

## 2. Esta semana

- **Patterns: cabos de código y datos en producción.** El código está en local y en
  Singapur (5 de septiembre). El catálogo de producción está **vacío**: hay que crear
  los patterns y enlazar cada producto. El 1947 de local no viajó; no se volcó la base.

  Un pattern es el corte (055_Semi). El producto es ese corte para una marca. Verdes =
  SKU + qty en el pattern (se leen en vivo). Amarillos = tipo + qty en el pattern; el
  SKU se elige en el color (Fill Pattern). Extras = por talla y por color. El producto
  no inventa una talla que el pattern no tenga. Los pedidos congelan el BoM.

  Lo que falta de código:

  1. **Borrar extras.** No hay botón. Hoy se vacía el SKU en todas las tallas de ese
     color y la línea desaparece.
  2. **Talla nueva en el pattern → productos ya hechos.** `ensureSizes()` solo corre
     al guardar un color, no al guardar el pattern. No borrar tallas en esa misma
     tarea.
  3. **Quitar talla del pattern.** Las tarjetas de talla quedan huérfanas; `wired()`
     falla y la pantalla no dice nada.
  4. **Duplicate product con pattern.** ECA parcheado en `11007` (copia el pattern
     nuevo, huecos y extras). **No comprobado.** Sigue copiando también el campo de
     Oscar (`field_tec_pattern`).
  5. **La URL de `+ Size`** (`/admin/content/tec_product/add/tec_size_variation?color_id=…`)
     sigue existiendo. El botón está escondido en productos con pattern.
  6. **Retirar el vocabulario de Oscar** (`tec_patterns` / `field_tec_pattern`) cuando
     Duplicate copie solo el pattern nuevo. Orden: quitar el paso de ECA → campo →
     vistas → vocabulario → permisos. No antes: duplicar producto es una función viva.

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

- ~~**Terminar el modelo de marcas y clientes.**~~ **Hecho el 19 de agosto de 2026**, incluido el
  catálogo con precio editable. Se había quedado en esta lista como si faltara la pantalla grande;
  esa pantalla **ya está**: ficha de la marca, `/taxonomy/term/{id}`, una fila por talla. Se llega
  desde `/b`. El corte del cliente, las cinco cosas de ese día y el rediseño deshecho (solo se
  acortó la casilla del precio) están en Hecho.

  **Y el precio de venta no está donde se supondría:** vive en la talla
  (`tec_product.tec_size_variation.field_tec_price`, etiquetado «Sales price»), no en el producto ni en el
  color. O sea que la pantalla para editar precios desde el catálogo de una marca es una lista de tallas
  agrupadas por color, no una lista de productos. Editarlas dentro de una vista no necesita nada nuevo:
  es lo que ya hace `views_entity_form_field` con las cantidades de `/o/draft/%`.

  El modelo acordado es: **los productos van dentro de la marca, y el cliente elige qué marcas le
  hacemos**, en su ficha y en el orden que decida el dueño. Los pasos, con los tres primeros hechos:

  1. ~~Un campo nuevo en las fichas de contacto —`tec_contact_organization` y `tec_contact_person`—, de
     varios valores y arrastrable, apuntando a marcas.~~ Hecho el 19 de agosto.
  2. ~~Pasar a ese campo lo que hoy diga el `field_tec_customer` de cada marca, y **retirar ese
     campo**.~~ Hecho el 19 de agosto, con la comprobación previa de que no se perdía ningún par.
  3. ~~Retirar el `field_tec_customer` del producto y **recablear los sitios que lo leen**.~~ Hecho el 19 de
     agosto: la vista que crea las proformas del pedido de un clic, la pestaña Products de la ficha del cliente
     —que además salió agrupada por marca—, la pantalla de *Organize products*, las columnas y filtros de
     las listas, el widget del formulario, el campo de la ficha del producto y el paso de la ECA que lo
     copiaba al duplicar.
  4. ~~Ordenar las líneas de la proforma en dos niveles: primero por el orden de marcas del cliente,
     después por el orden de productos dentro de la marca.~~ Hecho la noche del 19 de agosto, y en
     **cuatro** niveles: marca, producto, color y talla. El orden de los productos dejó de ser del cliente
     y pasó a ser de la marca, que es lo que hacía falta para que una proforma pudiera leerlo.
  5. ~~Borrar las filas de `draggableviews_structure` guardadas por cliente y añadir al guardián que la
     marca de un producto esté en la lista de su cliente.~~ Se dijo que se caía por sí solo, porque la
     pantalla de ordenar seguía recibiendo al cliente; **se hizo esa misma noche**, cuando la pantalla se
     retiró: las filas de pesos se borraron todas y ninguna vista menciona ya ese módulo. La regla del
     guardián sigue sin tener a quién contradecir, porque el producto ya no lleva cliente, y en su lugar se
     vigila que **ningún producto se quede sin marca** ni sin sitio dentro de ella.

  ~~**La decisión que sigue pendiente, y hay que tomarla antes de tocar la proforma:** el orden de las
  líneas de los pedidos de venta sale de los pesos que se arrastran en `/tec_crm/%/reorder`, así que si
  algún día ese orden cambia, los pedidos ya emitidos cambian el orden de sus líneas en pantalla.~~
  Decidida por el dueño el 19 de agosto: **el orden se congela al crear las líneas, como los precios.** Sale
  gratis, porque el orden pasa a ser la sucesión de los números de las líneas y todas las pantallas ordenan
  por ese número; volver a arrastrar una marca cambia la proforma siguiente y no las emitidas.

  **Y un cambio de significado que ya está en marcha**, no de pantalla: «los productos del cliente» son
  ahora «los productos de sus marcas», así que dos clientes que compartan marca ven exactamente la misma
  lista. Es lo que se quiere, y de paso arregló algo que estaba roto sin que nadie lo supiera: el segundo
  cliente de una marca no recibía nada en su pedido de un clic.

  **Lo que no se toca, y conviene recordarlo cada vez que dé miedo:** el cliente del *pedido*
  (`tec_order.field_tec_customer`) es un campo distinto que solo comparte nombre. La numeración, el IVA,
  los precios congelados, la cadena de compras y la de producción se quedan enteras.

## 3. Antes de que entren los empleados

~~Esto va en dos fases, decidido el 12 de agosto. **Primero entra solo Lukpla, y no a trabajar
sino a revisar**: tiene que ver el ERP entero y proponer cambios antes de que se empiece a usar,
porque hay partes del negocio, compras sobre todo, cuya lógica conoce ella y no el dueño. Ese es
el motivo de querer publicarlo cuanto antes. Después, ya con sus cambios hechos, entran los tres
empleados a usarlo de verdad.~~ **Caducado el 28 de agosto de 2026.** El negocio no ha usado el
ERP nunca; el siguiente paso no es “tres empleados a trabajar”, es **Lukpla + Noi en dual run
con las sheets**. Quién es quién y qué pasa con un supervisor futuro está en
[Quién opera](#quién-opera-y-adónde-va-el-trabajo).

**La primera fase se completó el 12 de agosto**: servidor configurado, los tres ajustes de
`settings.php` corregidos, parche verificado, publicado en `erp.anvfightgear.com` con
certificado. El detalle está abajo, en Hecho.

El resto de esta lista es trabajo técnico que **sigue haciendo falta** (correo sobre todo), no
el relato de quién entra. El plan de correo se reescribió la **noche del 31 de agosto** (Hecho):
nada de `erp@` ni de grupos para el cliente. Lo que queda es Workspace (dueño) y luego SMTP
en el servidor.

- **Programar el cron.** ~~**Lo único que queda es pegar una línea en el servidor el día del
  despliegue**~~ **Hecho el 20 de agosto de 2026:** `/etc/cron.d/erp-cron` cada 15 minutos como
  `www-data`. El lado de Drupal ya estaba como tenía que estar (comprobado el 15 de agosto): `automated_cron` no
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
- ~~**Crear el buzón `erp@anvfightgear.com`.**~~ **No.** Caducado el 31 de agosto de 2026.
  El ERP no autentica como un buzón fantasma: entra SMTP como `orders@`. El correo del sitio
  pasa a `info@`. `erp@` no se crea.
- ~~**Crear los grupos `info@` y `orders@`.**~~ Decisión del 28 de agosto, **caducada el 31**.
  Se llegaron a crear esa misma tarde y **se tiran**: un grupo no es un buzón. No hay
  historial de clientes que migrar. Relato en Hecho, 31 de agosto (noche).
- **Correo de función: usuarios, no grupos** (31 de agosto, noche). Cuatro Gmail:

  | Dirección | Qué es | Quién la abre |
  |---|---|---|
  | `admin@anvfightgear.com` | David | Solo él |
  | `artitaya@anvfightgear.com` | Lukpla | Solo ella |
  | `orders@anvfightgear.com` | Clientes, diseños, aduanas, ex-works, proforma del ERP | Los dos (delegación). Hoy es `coo@` |
  | `info@anvfightgear.com` | Web, SEO, contraseña y altas del ERP | Los dos (delegación). Usuario **nuevo** |

  1. Borrar los grupos `info@` y `orders@` para liberar las direcciones.
  2. Renombrar el usuario `coo@` → `orders@`. Dejar `coo@` de alias (aduanas y clientes
     viejos). Contraseña nueva; quitar teléfono y recuperación del supervisor. Nombre que
     ve el cliente: **Acta Non Verba**.
  3. Crear el usuario `info@` (un asiento más). Delegar `orders@` e `info@` a `admin@` y
     `artitaya@` (Gmail → Conceder acceso). Nadie trabaja con la contraseña del supervisor.
  4. **SMTP** por el relay de Google (`smtp-relay.gmail.com`, puerto 587, TLS). Allowed
     senders: *Only addresses in my domains*. IP del droplet: `188.166.255.1`. El ERP entra
     como `orders@` (contraseña de aplicación, en `settings.php` del servidor, no en git).
     El campo From del módulo SMTP se deja **vacío**. Proforma (cuando se construya): From /
     Reply-To `orders@`. Contraseña, cuenta creada por un admin, cuenta activada: From
     `info@`. Hoy el ERP **no manda la proforma** (solo se imprime). Drupal sí mandará los
     mails de cuenta en cuanto haya SMTP. Correo del sitio: cambiar `erp@` por `info@`.
     DigitalOcean puede tener cerrado el 587: si el test no sale, ticket para abrir SMTP
     hacia Google.
  5. **DKIM** (Google Admin → Namecheap) y **DMARC** `p=none`. SPF no se toca si ya incluye
     Google.

  Lo que sale del ERP, y nada más: familia **login** (`info@`) y, cuando exista el envío,
  **proforma** (`orders@`). Una conexión SMTP, dos From. No hace falta que el ERP «entre»
  como `info@`.

- ~~**Repasar las cuentas de usuario.**~~ **Hecho por el dueño el 15 de agosto de 2026**, a mano y
  desde el navegador. Quedan cinco cuentas contando el anónimo: `david`, que es la del dueño con rol
  de administrador, y las tres de los empleados, `lukpla`, `coo` y `manager`. Se fueron las cuatro
  que no eran de nadie de la empresa —`rat`, `david-antiguo`, `executive` y el `manager` viejo, dos
  de ellas con correo en `drusphere.com` y otra en `actafight.com`—. Neto, una cuenta menos, y el
  guardián del comprobador ya espera cinco.

  ~~**Queda ponerles la contraseña a mano a las tres nuevas.**~~ **Hecho por el dueño el 28 de
  agosto de 2026**, desde la pantalla de administración de usuarios en producción. Incluye
  `manager`, que sigue sin correo y por eso no puede usar el enlace de «olvidé mi contraseña»;
  `lukpla` y `coo` sí tienen dirección en el dominio de la casa.

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

**Lukpla testa en inglés** (31 de agosto de 2026). El dual run no espera a una traducción.

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
obligatorias** cubiertas y ninguna columna pidiéndose dos veces. El proveedor va a
`field_tec_vendor` resolviendo por título, el límite sube de 100 a **1.000 filas por pasada**, y
la creación automática de unidades y tipos queda **apagada**. Los seis términos duplicados, el
`Blue ` con espacio incluido, ya no están: se limpiaron en una fase anterior.

**El 20 de agosto las cabeceras pasan a ser las etiquetas del formulario de añadir material**
(`/admin/structure/taxonomy/manage/tec_inventory/add`): `Material name`, `Traceable`,
`Purchase Cost (No VAT)`, `MOQ (UoP)`, `Reorder Point (ROP) (UoS)`, `Safety Stock Qty (UoS)`,
`Importer item ID`. Se añade **Active** (`status`). Quedan **21 columnas**. **Stock level (UoS)
no se importa**: el stock inicial tiene que entrar como transacción, y de momento lo pondrán
los empleados a mano o se hará en un segundo paso.

Se probó importando de verdad, no solo validando: tres materiales creados con los veintiún campos
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

- ~~**Decidir qué se hace con Patrones.**~~ **Decidido el 5 de septiembre de 2026.** El
  corte de fábrica es la entidad `tec_pattern` (`/pattern`), no el vocabulario de Oscar.
  El producto apunta con `field_tec_factory_pattern`. En la UI se dice **Pattern**, nunca
  mould / molde / mold. Relato en Hecho, 5 de septiembre.

  El vocabulario `tec_patterns` y el campo `field_tec_pattern` **siguen**, vacíos y
  escondidos en el formulario del producto. Duplicate product (`process_llpx4tp` y el
  clon `process_icpsbgv`) todavía copia `field_tec_pattern`. **No se borra hasta que
  duplicar copie solo el pattern nuevo.** Entonces: quitar ese paso de ECA → campo →
  vistas `tec_patterns` / `tec_pattern_elements` → vocabulario → permisos.

  Lo que ya se sabía del 15 de agosto no cambia el orden: al retirar `tec_gui` se fue
  `field_tec_pattern_boms`; la vista `tec_pattern_elements` quedó desactivada y su
  bloque sigue incrustado en la ficha de un término. Hoy hay cero términos, así que no
  molesta. El día que se retire el vocabulario, se va con el resto.

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

- ~~**Dar de baja el servidor de Nueva York.**~~ Hecho el 28 de agosto de 2026 (ver sección 1).
- ~~**Borrar el snapshot `actafight.com-1755603370919`** (38,68 GB)~~ Hecho el mismo día, junto
  con la destrucción del droplet.
- ~~**Borrar de Drive la carpeta `PRE-limpieza`.**~~ **Hecho el 2 de septiembre de 2026.**
  El dueño la eliminó de Drive (y de la papelera). Ya no hay dos juegos de copias ni esa
  copia de la clave de OpenAI del programador anterior.

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
- ~~**Ventas: alinear las tres pantallas y ponerles el IVA.**~~ **Hecho el 30 de agosto de 2026;
  en el servidor el 31.** `/my` y fábrica usan la misma rejilla (columnas de `/my`, una lista,
  un pie). TH = 7 %, resto = 0 %, estampado en el pedido. Relato en Hecho esos días. El **papel
  PHP de la proforma** está en local y en el servidor (31). Falta la **proforma automática por
  correo** al confirmar.
- **Avance automático del estado del pedido** cuando "Remaining" llega a cero. Decidido
  dejarlo desconectado de momento; se puede conectar más adelante sin rehacer nada.
- **CBM estimado al armar el pedido**

  Antes de Confirm, el cliente (y fábrica) tiene que ver **aproximadamente** cuánto ocupa el
  pedido: si cabe en un 20' o un 40', y más o menos cuántas cajas para el flete EXW. Si se pasa
  del contenedor, el resto se queda en fábrica hasta el siguiente envío; no le gusta a nadie
  (cashflow). Clientes que no llenan contenedor también lo necesitan para cotizar el envío.
  **No es el packing list:** eso va después, con cajas y kilos reales.

  **Dónde vive el dato:** en la **talla** (`tec_size_variation`), no en el producto ni en el
  color. El 10 oz no llena la caja igual que el 16 oz; un saco `150×40` no es un `47×60×65`.

  Campo que se rellena a mano: **piezas por caja** (ej. guante 10 oz = 20). El CBM por pieza
  **no se teclea**: es `0,1833 ÷ piezas por caja`. Caja de fábrica fija: **47×60×65 =
  0,1833 m³**. Ejemplo: 20 pares → 0,009165 m³ por par. Si mañana caben 18, se cambia el 18;
  no un 0,0092 puesto a mano.

  **Sacos y lo que no va en esa caja:** no se dividen entre 0,1833. Llevan el CBM del propio
  bulto (1 saco = 1 bulto) y en el pie van **aparte**.

  **Pantalla:** la misma rejilla de Place an order — `/customer/{id}/order/new` y `/my` (y el
  pedido Open). Columna nueva **Approx. CBM**: por fila, `qty × CBM de cada talla` (suma de
  las tallas de esa línea). No un CBM dentro de cada celda de talla. Odoo haría
  `volumen × qty` del producto y mentiría; aquí se mezcla relleno de caja.

  **Pie** (orientativo, rotulado *estimated*):

  - total CBM de lo que va en caja `47×60×65`
  - ese total ÷ 0,1833 = **cajas aproximadas** (suma primero, luego divide; no redondear por
    línea; el redondeo hacia arriba ya es la última caja)
  - margen: **+1 caja**, no +2 (en un pedido chico +2 distorsiona el flete)
  - CBM de sacos / bultos sueltos
  - **CBM cajas + CBM sin caja** = lo que se mira para el contenedor

  Hasta que una talla no tenga piezas/caja, esa línea no aporta CBM. Sin maestro, la columna
  sería teatro.

  **No entra** en el packing list ni en el envío: ahí no va esta estimación ni el +1.

- **Envío, packing, factura y cargos (modelo del 31 de agosto de 2026, noche). No
  construir ahora.** Tarea aplazada a propósito: el dueño está saturado y el Excel sigue
  cubriendo el loading. **No se factura el pedido.** Si se imprime una factura desde
  `tec_order` (un pedido = un papel), el mundo real la desmiente a la semana. Por eso las
  facturas de venta **no se han hecho todavía**; no era un print que faltaba, era el modelo.

  #### Si solo se lee esto

  Tres sitios:

  1. **Pedido** — un encargo. Confirm → **proforma**. Depósito **de ese** encargo, con
     importe. Precio e IVA congelados (ya).
  2. **Línea** — pares (producto, color, talla) **o** cargo (texto + precio: pegatinas,
     desarrollo de patrón, packaging especial). El cargo no es catálogo, no hay BoM, no hay
     caja.
  3. **Envío** — este contenedor / esta recogida EXW. De **aquí** salen el **packing list**
     (sin precios) y la **factura** (con precios, IVA, cargos, menos depósitos). Un SWIFT de
     saldo.

  El pedido es la **promesa**. El envío es lo **hecho**. Odoo y SAP facturan la entrega, no
  el pedido. Aquí la entrega es el envío, porque sois fábrica EXW y un contenedor, no un
  almacén con doce albaranes al día.

  #### Por qué el 1:1 pedido–factura no vale

  Pasa de verdad:

  - El cliente confirma, paga el depósito, a la semana **añade productos**. Se hace **otra
    proforma**. Quiere las **dos** en el **mismo** contenedor y **una** factura (un
    transfer internacional; no uno de pegatinas).
  - Un pedido **no cabe**: Remaining, **dos** contenedores, dos packing, **dos** facturas
    (cada una lo que salió). Ya está escrito en el CBM y en las sheets.
  - Hay que cobrar cosas **que no son producto**: pegatinas, product development, packaging
    especial. Casi siempre **una vez**. El cliente **no** quiere un segundo SWIFT de 200 €.
    Van en la **misma** proforma de ese encargo y en la **factura del contenedor**.

  Lo contrario también: dos encargos, un barco. El Excel mezcla las tres cosas en una hoja.
  El ERP las separa y el envío las vuelve a juntar **solo para esa salida**.

  #### Qué hace Odoo / SAP (lo que se copia)

  Regla: política de factura = **lo entregado**, no lo pedido.

  - Pedido + print de proforma. Anticipo con **importe**; la factura final lo resta.
  - Entrega parcial → **backorder** = Remaining. Otra entrega, otra factura.
  - Varias entregas del mismo cliente → **factura colectiva** (Odoo: juntar albaranes;
    SAP VF04). Ahí caben dos proformas y un contenedor.
  - Cargos = producto **servicio** (sin stock). No van al albarán; sí a la factura.
  - SAP tiene **shipment** (camión/contenedor) que agrupa entregas; el packing sale de ahí.
    Odoo de serie **no** tiene el contenedor: por eso muchas fábricas siguen con Excel de
    loading. ANV no instala Odoo; construye **un** objeto envío que es picking + shipment.

  No se copian pistolas, handling units, SSCC ni 400 módulos.

  #### Cómo queda en ANV

  **Pedido (`tec_order` de venta), ya existe.** Confirm sella. Proforma = el print PHP de
  ahora (`/o/pf/{id}/print`). Añadir pares después = **pedido nuevo**, proforma nueva, otro
  número. No reabrir ni reescribir el 26-001 (el cliente ya lo archivó). El envío junta
  26-001 y 26-002.

  Depósito: hoy hay un sello (`field_tec_accounting_verified_on`). Para la factura hace
  falta **cuánto** (fecha, importe, a qué pedido). Si pagaron “más o menos el 30 %”, la
  factura enseña la cuenta.

  **Cargos, en el pedido.** Tres tipos bastan: product development, stickers/artwork,
  special packaging. En la línea, descripción libre (“Pegatinas logo Rise, 5.000 uds”).
  Precio congelado, igual que el guante. **Historial** = el pedido; la próxima vez se
  propone el último cobrado a ese cliente por ese tipo, para no inventar el precio. No
  viven en `/b` ni en Place an order (eso lo apunta fábrica/David al cerrar). El re-order
  del portal **no** los copia. Si el cargo es único (un patrón), no se vuelve a facturar;
  el dato sigue ahí. Si se repite (otra tanda de stickers), **línea nueva**.

  Van a la **proforma de ese encargo** (si no, el depósito no los cubre). Van a la
  **factura del primer envío** que saque mercancía de ese pedido (regla simple; el 90 %).
  **No** van al packing list (no hay bulto). **No** una factura suelta de stickers. Si el
  logo llega **después** de cobrada la proforma: anexo/proforma nueva con la diferencia, o
  el extra en el saldo del embarque si aún no está el 100 %. Nunca un SWIFT minúsculo.

  Aduanas: el desarrollo **mejor no** hinche el precio unitario de los pares. Si un día
  hace falta un papel solo de mercancía para el forwarder, es un **print del mismo envío
  sin NRE**, no otro cobro. Pegatinas **en** el producto pueden ir en el valor de la
  mercancía.

  **Envío (nuevo).** Cliente, fecha, EXW. Líneas con la **cantidad de este barco**,
  cogidas de **uno o varios** pedidos del mismo cliente. Lo pedido menos lo ya enviado =
  Remaining. Sold to y Ship to de verdad (pueden no ser el mismo).

  Dos papeles, al **cerrar el envío** (no al Completed del pedido en la cola):

  | Papel | Qué lleva |
  |---|---|
  | Packing list | Cajas, piezas, NW, GW, CBM **real** de este barco. Sin precios. Sin cargos. |
  | Factura | Los mismos pares, **con** precios congelados, IVA (TH 7 % / resto 0 %, el ya estampado). Cargos. Menos depósitos de los pedidos de este envío. Un total. |

  Un envío = una lógica de IVA. No mezclar pedido Tailandia y export en el mismo
  contenedor/factura. Con IVA y sin IVA = el mismo papel; cambia el pie.

  La cola: Completed = “ya se puede meter en un envío”, no “imprime la factura de este
  pedido”. Ready for collection = el forwarder puede recoger **este** envío.

  **Packing (detalle que se mantiene del Excel, no la hoja).** Hoy: *Packing list Order
  Custom Fighter 25-001* a mano. No se copia el Excel (celdas combinadas, una fila por
  caja aunque sean iguales, *Code* = color, comentarios *Missing 1 pair*, Sold to clonado
  en Ship to). Sí: cajas mezcladas (hay que detallarlas); 1 saco = 1 caja; cartón
  `47×60×65` → 0,1833 m³; bruto ≈ neto + ~2 kg; pares vs units; membrete EXW / origen.
  No: 18 filas idénticas de caja llena en el PDF del cliente; el PDF como checklist de
  planta. *Missing 1 pair* es planta / production log, no el papel comercial. Cajas hijas
  del envío; peso y CBM son de la **caja**. El Approx. CBM del Place an order **no** entra
  aquí.

  #### Qué no se hace

  Facturar `tec_order`. Fusionar pedidos. Producto fantasma en el catálogo. Factura de
  cargos suelta. Que el portal arme el envío (es el día del loading, fábrica). Avance
  automático a Completed cuando Remaining = 0 (sigue desconectado, decisión previa).
  Copiar Odoo/SAP entero.

  #### Orden el día que se construya (no ahora)

  1. Entidad envío + cantidades de este barco + packing list.
  2. Factura de ese envío (IVA ya existe).
  3. Cobros con importe, restados en el pie.
  4. Cargos en el pedido → proforma y factura.
  5. Mail de proforma al confirmar (otro pendiente; no bloquea lo de arriba).

  Hasta el paso 1+2, una “factura” en el ERP mentiría igual que el 1:1. El Excel de
  loading sigue siendo válido **hasta entonces**.
- **Web pública de `anvfightgear.com`**, de presentación de la fábrica OEM. **La estructura, el
  argumentario y el análisis de la competencia ya están escritos en
  [`docs/web-anvfightgear.md`](web-anvfightgear.md)**, listos para pasárselos a otra ventana de
  agente. Lo único que bloquea el trabajo son las fotos y el vídeo del taller, que solo puede
  hacer el dueño. Corrección del 12 de agosto: el dominio no muestra una página de
  aparcamiento, simplemente **no tiene ningún registro A**, ni él ni `www`. No hay nada roto
  que arreglar.
- ~~**Portal de clientes**~~ **Hecho el 29 de agosto de 2026** (código en producción,
  **cero cuentas Customer**). Alta: [`docs/sop-give-a-customer-a-portal-login.md`](sop-give-a-customer-a-portal-login.md).
  El 30, en local, fábrica Place/Confirm igualó a `/my` (`/customer/{id}/order/new` → `/o/order/{id}`).
  Eso y el IVA **ya están en el servidor** (31 de agosto). Falta la **proforma automática por correo**
  y el **SOP de cierre de venta**. Packing, factura de venta y cargos: modelo escrito el 31
  de agosto (noche), **sin construir**. Requisitos originales en [`docs/customer-portal.md`](customer-portal.md).
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
9. ~~Destruir el de Nueva York.~~ **Hecho el 28 de agosto de 2026.**

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

### 2026-09-05 — Factory Pattern: el corte es una entidad, no el vocabulario de Oscar

Un pattern es el corte de fábrica. En código y pantallas se dice **Pattern**, nunca
mould / molde / mold. El producto es ese corte para una marca. 055_Semi es el mismo
corte para todos los clientes; el nombre comercial vive en el producto.

Verdes = SKU + qty en el pattern, se leen en vivo en la ficha del producto. Amarillos =
tipo + qty en el pattern; el SKU se elige en el color (Fill Pattern). Extras (stickers)
= por talla y por color. El producto no inventa una talla que el pattern no tenga.
Cambiar qty o SKU verde en el pattern actualiza los productos al momento. Los pedidos
ya hechos congelan el BoM.

Pantallas: `/pattern` (lista, columna Products) y `/pattern/{código}` (ficha, productos
encima de las tallas). Alta de producto: `/p` → Product → editar colores → Fill Pattern
→ `/tec_product/{id}`. Extras se guardan solos (`POST /tec_product/{color}/extras`).
No hay botón de borrar extra: se vacía el SKU en todas las tallas de ese color.

En productos con pattern se esconden + Size, Duplicate size y Duplicate variation.
Quedan + Variation y Duplicate product. `ensureSizes()` corre al guardar un color, no
al guardar el pattern.

Oscar `tec_patterns` / `field_tec_pattern` sigue vacío y escondido. Duplicate todavía
copia ese campo; se retira cuando duplicar copie el pattern nuevo (`field_tec_factory_pattern`).

**Singapur, 5 de septiembre:** código de `tec_inventory` + icono HOME (nid 20). Esquema
**11007–11009** (el 11006 ya estaba). `/pattern` responde 200. Catálogo **vacío**: no se
volcó la base local. Script: `scripts/poner-patterns-en-servidor.sh`.

Pruebas: `scripts/el-patron.php`, `scripts/el-producto-del-patron.php`. La de humo
(`comprobacion.php`) mira la entidad, las rutas, los permisos y el icono.

### 2026-09-02 — Carpeta PRE-limpieza, fuera de Drive

El dueño borró en Google Drive la carpeta `2026-08-12 PRE-limpieza - NO usar para
desplegar` (los zips de la mañana del 12 de agosto, con la clave de OpenAI) y la
sacó de la papelera. Nueva York ya no existe; las copias vivas son GitHub y las
diarias de `erp-anv-sgp1`.

### 2026-08-31 (noche) — Factura de venta: el modelo, no el código

No se construye. El dueño saturado; se deja escrito para no reabrir el debate. Las
facturas de venta no se habían hecho porque un print del pedido **no** aguanta el mundo
real (segunda proforma a la semana, mismo contenedor, una factura; Remaining a otro
barco; pegatinas y desarrollo en el mismo SWIFT).

Se factura el **envío**, no el pedido. Relato entero en la sección 6, «Envío, packing,
factura y cargos». Excel de loading hasta que exista esa pieza.

### 2026-08-31 (noche) — Correo: usuarios `orders@` e `info@`, no grupos ni `erp@`

Los grupos no sirven para ANV. Un grupo no tiene Gmail: es una lista. `orders@` es un
mostrador (hoy vive en `coo@`): pedidos por mail, productos y diseños nuevos, agentes de
aduanas, recogidas EXW, ~15 clientes y más adelante más. Ahí hace falta **una bandeja**,
leídos compartidos, etiquetas. Amazon usa grupos para listas internas, no para eso.

Se tiran los grupos creados esta tarde (`info@`, `orders@`; no hay historial que mover).
No se crea `erp@` (acordado el 12 de agosto como buzón de sistema: caduca). El correo del
sitio pasa a `info@`.

Plan: `coo@` se **renombra** a `orders@` y `coo@` queda de alias. Usuario nuevo `info@`
(un asiento). Las dos se **delegan** a `admin@` (David) y `artitaya@` (Lukpla). Nombre de
cara al cliente: **Acta Non Verba**. Contraseña nueva en la cuenta del supervisor; quitar
su teléfono y recuperación.

El ERP, cuando mande, entra SMTP **una vez** como `orders@` (relay de Google) y pone el
From que toque: proforma → `orders@`; contraseña y altas de cuenta → `info@`. Hoy solo
existe la impresión de la proforma; el envío es trabajo de la sección 6. Módulos nuestros
no mandan ningún otro correo. Relato vivo y pasos en la sección 3.

Caduca la entrada del 28 de agosto (noche) «Correo de función: grupos».

### 2026-08-31 — /p y el catálogo de la marca: rotos desde las 14:46, ya vueltos

Al desinstalar `draggableviews` Drupal se llevó la vista `tec_products` entera:
en la config **activa** todavía lo nombraba como dependencia, aunque el YAML de
disco ya no. Sin esa vista no hay lista en `/p` ni catálogo en
`/taxonomy/term/{id}`. Los productos no se tocaron (Rise Fight Gear sigue
teniendo las nueve tallas). Vista, campo del catálogo y entity browser
repuestos desde `config/sync` en local y en el servidor. El guardián ahora
pregunta si la vista existe; la prueba de humo ya no da por buena una página
con el bloque gris. Script: `scripts/devolver-la-vista-de-productos.php`.

### 2026-08-31 — El catálogo editable de la marca ya estaba

Vive en la ficha de cada marca, `/taxonomy/term/{id}`, y se llega desde `/b`.
Una fila por talla, precio editable, casilla al 40% del ancho. Hecho el 19 de
agosto; seguía en la lista de esta semana por no tacharlo.

### 2026-08-31 — Dual run arrancado; lo que ve Lukpla va por LINE

Lukpla (con Noi) usa ya el ERP y las Google Sheets a la vez. Lo que falla lo
manda en un grupo de LINE; David lo arregla. Así se hizo hoy el Download de
las facturas. No hay hoja de bugs. Dual run hasta que el ERP aguante; entonces
se cierra Drive.

### 2026-08-31 — Lukpla testa en inglés

El ERP está en inglés y el tailandés configurado no traduce las pantallas
nuestras. Lukpla se maneja en inglés; el dual run no espera a una traducción.
Un operario de planta con usuario sería otro asunto.

### 2026-08-31 — Git en Windows ya no finge 180 cambios vacíos

Laragon trae `core.autocrlf=true` en el git global. Drupal pide LF. El
repositorio ya guardaba LF; en disco muchos scripts se reescribían con CRLF
y `git status` los pintaba sucios con diff vacío. En este repo queda
`core.autocrlf=false` y `core.eol=lf` (el git global de Laragon no se toca).
`.gitignore` y `*.ps1` pasan a `eol=lf` en `.gitattributes` (el andamio de
Composer lo vuelve a pegar). Cursor, en este proyecto, guarda `\n`. Drupal
11.4.5 arranca. Los parches siguen en LF. **No usar** `git add --renormalize .`
si vuelve a pasar: restaurar los que no tengan diff de verdad.

### 2026-08-31 — Duplicar ya no ensucia el registro

Oscar dejó Logs de depuración en los procesos de duplicar. Un clic escribía una
veintena de líneas, muchas con el hueco sin rellenar (`%token__entity`). Fuera
de los tres duplicados y de los procesos que se disparan al guardar las fichas
nuevas. El aviso al usuario (*Color magically duplicated.*) se queda. En local
y en el servidor. Sin esquema nuevo.

### 2026-08-31 — Desinstalado `draggableviews`

Desde el 19 de agosto no ordenaba nada: la pantalla de arrastrar y las filas de
pesos ya no existían. Fuera en local y en el servidor. Sin esquema nuevo.

### 2026-08-31 — Packing list: se miró el Excel y no se copia

El dueño pasó *Packing list Order Custom Fighter 25-001* (Google Sheet / xlsx). Hoja Final:
148 cajas, 1.902 piezas, mix de colores y tallas en la misma caja, sacos 1=1 caja, Remaining
= lo que no cupo en el contenedor. No se va a clonar esa hoja. Las tareas quedan en
Funcionalidad nueva: **CBM estimado al armar el pedido** y **Packing list**.

### 2026-08-31 — Nombre de las fotos de color variation: a otra ventana

Patrón acordado: `Marca_Producto_Color_01.jpg` (si el nombre ya lleva la marca, no
repetirla). Fotos en `field_tec_images` de la color variation, no en la talla. El dueño lo
encargó a otra pestaña de agente; aquí no se implementó.

### 2026-08-31 — Línea vertical en el centro de la proforma

Misma raya que Date/Company (`1px #c8c8c8`). Mitad y mitad. El bloque del
cliente va al borde izquierdo del A4. El hueco a cada lado de la raya es el
mismo que el margen lateral (`8mm`). En local y en el servidor. Sin esquema nuevo.

### 2026-08-31 — Orden de filas en la proforma

Cliente: Company, VAT ID, Country, Address. Membrete: Company, Tax ID, Country,
Address, Email, Phone. El país de ANV ahora sale. Sin esquema nuevo.

### 2026-08-31 — Sin logo en la proforma impresa

El papel no lleva el logo. PROFORMA y cliente a la izquierda, Acta Non Verba a
la derecha. El logo de Company settings se queda para la barra, no para el
print. Sin esquema nuevo.

### 2026-08-31 — Nombre de factura abre, Download guarda

En File invoice el nombre del scan abre `/system/files` en otra pestaña. Download
sigue guardando el archivo. Sin esquema nuevo.

### 2026-08-31 — Download de la factura descarga el archivo

El enlace abría el scan en una pestaña (`target="_blank"` + JPEG inline). El
botón se llama Download: tiene que ir a Descargas. Ruta
`/tec_order/{id}/invoice/file` con `Content-Disposition: attachment`. Sin
esquema nuevo.

### 2026-08-31 — Cabeceras de tabla al estilo ERP

Más chicas que las filas (0.75 rem / 7 pt), semibold, gris, mayúsculas por CSS
(`text-transform`), no `PRODUCT` en el PHP. En pantalla: Product / Material (antes
Product name / Product material). En el print: sin título Image; la columna ya es
la foto. Sin esquema nuevo.

### 2026-08-31 — El icono de print ya no se queda girando

Oscar escondía el `<img>` y ponía un spinner. El print abre otra pestaña
(`target="_blank"`), la lista no recarga y el icono no volvía. El script
`tec_link_spinner` ahora mira el `<a>` del clic. Esquema portal **8013**.

### 2026-08-31 — Ajustes del papel de la proforma

Sin letrero PRO FORMA; la etiqueta a la derecha es **PROFORMA**. Sin BILL TO:
el cliente va debajo de la fecha. Cabeceras cortas. Filas (productos, sin
foto, Subtotal/VAT/Total) a la misma altura. La URL al pie es Chrome.

### 2026-08-31 (noche) — El papel de la proforma cabe en A4

Márgenes `@page`, tabla de 8 columnas apretada, PRO FORMA sin recortar.
Las miniaturas ya no van `loading="lazy"`. El diálogo de imprimir espera a
las fotos; el `print()` inmediato de Oscar (`tec_print_browser`) y su CSS
de 21 cm quedan solo en `/po/{id}/print`. Esquema portal **8012**.

### 2026-08-31 (noche) — El print de la proforma es PHP

Misma URL `/o/pf/{id}/print` para no tocar iconos. `page_4` de Oscar se apaga
(la View no se borra). Papel: membrete de Company settings, PRO FORMA, número,
fecha de alta del pedido, Bill to, la misma tabla de 8 columnas que `/o/order`
(Image … Item total + pie IVA), banco solo si hay datos. Open/Cancelled: 403.
Fábrica o el cliente dueño. Compras `/po/{id}/print` no se toca. Sin correo.
Esquema portal **8011**. Script: `scripts/poner-la-proforma-en-servidor.sh`.

### 2026-08-31 (noche) — El nombre en `/o` abre `/o/order/{id}`

Como en `/my`. `/o` (block_1) y la pestaña Orders del cliente (block_2). La ficha
`/tec_order/{id}` sigue en la cola y escribiendo la URL. Compras (block_3) no se
tocan. Esquema portal **8010**. Script: `scripts/poner-el-nombre-del-pedido-en-servidor.sh`.

### 2026-08-31 (noche) — Line items de la ficha es la tabla de `/o/order`

La pestaña Line items de `/tec_order/{id}` ya no pinta la View de Oscar
(`tec_order_sales_order_line_items-block_1`). Pinta la misma tabla PHP de
`/o/order` (Image, Colour, pie IVA, miniatura). Bloque `tec_portal_sales_order_lines`.
La View no se borra (`page_4` era el print; **8011** la apaga). Material calculation,
Material Summary y Production Documents no se tocan. Revertir: el bid del Quick Tab
a `views_block:tec_order_sales_order_line_items-block_1`. Esquema portal **8009**.
Script: `scripts/poner-la-tabla-de-lineas-en-servidor.sh`.

### 2026-08-31 (noche) — «Order status» ya no coge el color de la pastilla

En la ficha, Field Formatter Class ponía las palabras del estado como clases del
envoltorio entero. El CSS pintaba `div.Pending.payment` y la etiqueta iba roja
con el valor. Token quitado; el CSS de Oscar queda solo bajo `.views-field`.
Esquema **10007**. Ya en local y en el servidor.

### 2026-08-31 (noche, más tarde) — Las Views de `/o` ya no son Oscar

El PHP de Place/Confirm ya estaba. El listado Orders (`node/3`, `/o`, bloque
`tec_orders_orders-block_1`) seguía con los lápices a `/o/draft` y la impresora en Open.
Esquema portal **8003 → 8008** (`8005` apaga `/o/draft`, `8007` quita `?destination=` de
ventas, `8008` Open = lápiz a `/o/order`, después = Print Proforma). Un `/o/draft/{id}`
redirige a `/o/order/{id}`. Compras `/po/draft` no se toca. Script:
`scripts/poner-vistas-de-pedido-en-servidor.sh`.

### 2026-08-31 — Place de fábrica, IVA de venta, membrete y logo PNG en producción

Copiados `tec_portal`, `tec_production` y `tec_crm_ux` a `/var/www/erp`. Esquema **10004 → 10006**
(`10005` campo IVA en ventas, `10006` Company settings). **No se reejecutó 10004.** No se pisó
`tec_production.settings`: el 7 % y el nid del icono de cola se quedaron. PNG público
`sites/default/files/anv-logo.png` (HTTP 200). Gin y DXPR apuntan ahí; el login ya enseña el
icono. País obligatorio en clientes (orgs) viajó con `tec_crm_ux`.

Scripts: `scripts/poner-iva-y-pedidos-en-servidor.sh` / `.php`. No se importó `config/sync`
entero. No se volcó la base local. Lo que sigue fuera: correo al confirmar, Cancel en `/my`.

### 2026-08-31 — El logo de la barra es un PNG público; Appearance de DXPR no se toca

El logo de **Company settings** es para el papel. Los círculos de la barra, HOME y la pestaña
son otra cosa: Gin y DXPR. Subir un PNG en Appearance lo copia a **privado** (`default_scheme:
private`) y el navegador enseña el icono roto. Guardar
`/admin/appearance/settings/dxpr_theme` reescribe el tema entero (es un diseñador, no un
formulario de logo).

Copia pública `sites/default/files/anv-logo.png` (sin espacios). Gin y DXPR apuntan ahí.
Favicon: el mismo PNG. Ese fichero **no va en git**; si se importa la config en el servidor
sin copiarlo a mano, HOME se rompe otra vez. **Ya está en** `https://erp.anvfightgear.com`
(el mismo 31).

### 2026-08-30 (noche, más tarde) — Identidad de la empresa en Company settings

`/admin/config/tec/company` tenía el 7 % y los iconos del home. Le faltaba quiénes somos:
razón social, dirección, tax ID, teléfono, email, web, logo, banco. Defaults del membrete que
estaba pegado en el HTML de compras (Acta Non Verba, Nongmaikaen, Pattaya). Tax ID, banco y
logo vacíos hasta que se rellenan. El logo de esa pantalla es **para imprimir**, no para la
barra. Esquema **10006**. Clase `Company`. Prueba: `se-tocan-los-ajustes.php`.

**Ya está en el servidor** (31 de agosto). El print de proforma lo lee desde **8011**.

### 2026-08-30 (noche) — IVA de venta: Tailandia 7 %, fuera 0 %, país obligatorio

ANV está dada de alta. El destino manda, no cómo tribute el cliente como proveedor. TH = tipo
de `tec_production.settings` (7 %). Cualquier otro país = 0 %. `field_tec_vat_treatment` no se
toca: la misma ficha puede ser cliente y proveedor.

Líneas en neto. Pie Subtotal / VAT / Total en Place, Open, cerrado, ficha (`block_1`) y
proforma (`page_4`). En `/my` el Total de la lista es el **bruto**. El % se estampa al crear
el pedido (y en Open si el país llega después). Un pedido ya estampado no se reescribe si el
cliente cambia de país. Pedidos viejos sin %: un solo Total, como antes.

Place y Confirm exigen país en la organización. El selector es obligatorio **solo en
clientes**, no toda la dirección (si no, Address preselecciona EE. UU.). Cambiar de país
vacía la provincia que el país nuevo no tiene; si no, el ajax de Address dejaba Chon Buri en
Singapur y el guardado fallaba. Esquema **10005**. Pruebas: `el-iva-de-venta.php`,
`el-pais-del-cliente.php`.

**Ya está en el servidor** (31 de agosto). El papel PHP del print es **8011**. El correo al
confirmar, después.

### 2026-08-30 (tarde–noche) — Place y Confirm: fábrica igual que `/my`

`/my/order/new` es el modelo: Image | Product name | Material | Colour | Size | Qty | Price |
Item total; marcas seguidas, un solo total; solo el botón **Place order**. Fábrica no usa
`/o/draft/…` para ventas. Place: `/customer/{id}/order/new`. Open/Confirm: `/o/order/{id}`.
Misma entidad, misma rejilla, el cliente va en la URL no en el login.

Confirm guarda aunque no hayas pulsado Save. El icono Print proforma **solo después de
Confirm**, igual en `/my` y en fábrica. Tooltip: **Print Proforma**. Place an order en pestaña
nueva ya no rebota a la ficha. El `?destination=` de Edit se limpió. Pruebas:
`el-pedido-de-fabrica.php`, `el-pedido-del-portal.php`.

**Ya está en el servidor** (31 de agosto). Place: `/customer/{id}/order/new`. Open/Confirm:
`/o/order/{id}`.

### 2026-08-30 (tarde) — Los estados de venta son el flujo de la fábrica, no el de Oscar

Oscar no conocía el flujo. Accounting Verified no era un estado: era el clic de «ha llegado el
pago». Quality Control tampoco. Cadena:

Open → Pending payment → Processing → Ready for production → On production → Completed →
Ready for collection → Shipped. Cancelled se queda. Un pedido con número **no se borra**
(Cancel en `/my` aún no existe).

Las claves internas no cambian (`pending_deposit`, `ready_for_delivery`, …); cambian las
etiquetas. Pedidos viejos: Accounting Verified → Processing, QC → On production. Un solo mapa
`SalesStatus`: cola, ficha, clic «Update to …» (solo adelante). Open no se sella desde la
cola; se sella con **Confirm**. Completed y Ready for collection **siguen en `/o/queue`**
hasta Shipped (EXW) y **no** comen capacidad.

El cliente en `/my` ve Open / Pending payment / **Confirmed** (de Processing a Completed) /
Ready for collection / Shipped. Pending payment en rojo. El ECA de ese flag se apagó. El flag
del cliente dice **Place an order**. Esquema **10004**.

**Ya está en** `https://erp.anvfightgear.com` (esa tarde). Lo que vino después (rejilla,
fábrica Place, IVA, membrete, logo PNG) viajó el 31.

### 2026-08-30 (mañana) — Supplier orders: icono, factura, una línea, nombre de PDF

En producción no había atajo en el home a `/supplier-orders`. Icono en la portada, icono de
factura en el listado, filas en **una** línea, marco blanco más ancho para que quepan los
iconos. Los PDFs de factura se renombran
`INV_PO_DDMMYYYY_PROVEEDOR_26-013.pdf`. Vista previa al pasar el ratón: **no**, el Brother
escanea en PDF y no es el mismo truco que las fotos de producto.

**Ya está en el servidor.**

### 2026-08-29 — Portal de clientes en producción; aún no hay cliente real

Módulo `tec_portal` en `https://erp.anvfightgear.com`. `/my` pide login. El rol
**Customer** quedó en **7 permisos de ver** (antes 19, incluidos crear producto e
inventario). **Ningún usuario** tiene ese rol. No se copió la base local (Prueba LTD /
ChrisPruebaLTD / PRUEBA 26-013 se quedan en `tec.test`).

Recorrido: login → `/my` → Place an order → cantidades → Confirm → estado interno
`pending_deposit` (el cliente ve **Pending payment**). Misma entidad `tec_order` que
fábrica. Confirm **guarda** aunque no hayas pulsado Save quantities. Mientras está Open, la
rejilla es el catálogo entero (`/my/order/new` y `/my/order/{id}` se ven igual): se pueden
rellenar tallas que estaban a 0. Fotos 40×40 con hover. Pie: piezas bajo Qty, importe bajo
Item total. Texto al confirmar: *This order can no longer be changed. The order will be
processed after payment is received.* Estados del cliente más grandes y con color; Pending
payment en rojo. Date en `/my` es cuando fábrica marca el pago
(`field_tec_accounting_verified_on`), no la fecha de creación. La fila de la cola sigue gris
hasta Processing (el 30 se retiró Accounting Verified; no esperar ese nombre).

Identidad: usuario → **Contact person** → **Works at** → organización → marcas.
Nunca mezclar Customer con roles de fábrica. Las URLs `/tec_order/…` y `/customer/…`
rebotan a `/my`.

SOP de alta (nombre mejor que “Add new customer to the portal”, porque el cliente
ya está en el CRM):
[`docs/sop-give-a-customer-a-portal-login.md`](sop-give-a-customer-a-portal-login.md)
—*Give a customer a portal login*. El SOP de **cerrar la venta** (MOQs, materiales,
colores) sigue pendiente.

Pendiente de portal: correo de proforma al confirmar. Place de fábrica e IVA: en el servidor
el 31 de agosto.

### 2026-08-28 (noche) — Correo de función: grupos, no alias ni asientos nuevos

**Caducado el 31 de agosto de 2026 (noche).** Los grupos no son un buzón de trabajo. Ver
la entrada de esa noche y la sección 3. El texto de abajo es el plan que se tiró.

El cliente no debe escribir a un Gmail personal. Las direcciones de trabajo son de **función**
y se montan como **grupo de Google Workspace**, no como usuario de pago ni como alias de una
sola persona. Un alias de `admin@` solo vería David. Un usuario nuevo para `info@` u `orders@`
cobraría otra licencia y no hace falta: el grupo es una dirección extra del dominio y entrega
a los miembros.

| Dirección | Qué es | Para qué |
|---|---|---|
| `admin@anvfightgear.com` | Usuario (David) | Oficina general |
| `artitaya@anvfightgear.com` | Usuario (Lukpla) | Día a día |
| `info@anvfightgear.com` | Grupo (aún no existe) | Quien llega por la web / SEO |
| `orders@anvfightgear.com` | Grupo (aún no existe) | Pedidos, proformas, portal |

Miembros de **los dos grupos**: `admin@` y `artitaya@`. Quien escribe a `info@` u `orders@` les
llega a los dos. Responden **como** esa dirección (Gmail → Enviar correo como), no como ellos.
Gente de fuera tiene que poder publicar: si no, la respuesta del cliente rebota.

No se puede forzar «Responder a todos» en el Gmail del cliente. El grupo evita ese problema: el
cliente solo ve `orders@`. Si Artitaya no está, David sigue el mismo hilo.

El ERP, cuando mande la proforma (paso 7 del portal), usará **From / Reply-To `orders@`**. SMTP
autentica con un usuario que ya exista (`admin@` o `erp@`); el grupo no tiene contraseña. `erp@`
sigue siendo el buzón de sistema acordado el 12 de agosto; no es la cara que ve el cliente en un
pedido. Crear los dos grupos y el «enviar como» es trabajo en Workspace, no en Drupal. SMTP,
DKIM y DMARC siguen pendientes más arriba.

### 2026-08-28 — Quién opera: Lukpla testa, no hay supervisor a lo antiguo

El dueño fijó el organigrama y el destino del trabajo del supervisor que se fue (1,5 años).
Lukpla es socia y testa el ERP **en paralelo con las Google Sheets** (con Noi). Las ventas
(primer contacto → cierre) son solo de David; el supervisor nunca vendió, recogía cantidades
de re-orders. El plan de agosto «Lukpla solo revisa y luego entran tres empleados» queda
tachado en las secciones 1 y 3. Texto vivo al principio de este archivo.

### 2026-08-28 — GitHub al día; Cursor ya ve Git

El aviso de Cursor («Git no está instalado») era eso: no encontraba `git.exe`. Se le apuntó a
el de Laragon (`C:\laragon\bin\git\bin\git.exe`). La carpeta `.git` no se había perdido; el
remoto seguía siendo `https://github.com/daviddogu-code/ERP-ANV.git`. Había **cinco commits**
solo en este PC (coste del BoM, catálogo de marca, foto al pasar el ratón, cantidades
negativas); se subieron. El resto del trabajo de agosto que aún no estaba confirmado —cola de
producción, orden del catálogo, numeración de pedidos, corte del Excel Lover, guiones de
despliegue y este backlog— va en el commit de esta tarde.

**No se han metido** los ~70 ficheros que `git status` marca cambiados y `git diff` deja
vacíos (finales de línea). Siguen en «Esta semana».

### 2026-08-28 — Contraseñas de los tres empleados en producción

El dueño puso contraseña a mano a `lukpla`, `coo` y `manager` en
`https://erp.anvfightgear.com`. `manager` sigue sin correo electrónico; la recuperación por email
no aplica a esa cuenta hasta que se configure SMTP del ERP o se le añada una dirección.

### 2026-08-28 — Cierre del servidor de Nueva York

Destruido en DigitalOcean el droplet **`actafight.com`** (NYC3, 4 GB / 2 vCPU / 80 GB,
`174.138.49.12`). No tenía IP reservada. El dueño borró también el snapshot
`actafight.com-1755603370919` (38,68 GB); no queda disco del ERP viejo en DO. Producción sigue
solo en **`erp-anv-sgp1`** (SGP1, `188.166.255.1`, `https://erp.anvfightgear.com`). Ahorro
aproximado: unos 45 USD/mes frente al solape de los dos servidores.

### 2026-08-20 — Despliegue total a Singapur y ajustes de la cola de producción

Reemplazo de código, base de datos y ficheros en `erp-anv-sgp1` (Drupal **11.4.5**). Cron del
sistema en `/etc/cron.d/erp-cron`. Comprobación en servidor **220/223** (fallos menores:
`tec_sizes`, ficheros, bandera de ECA). En la cola (`/o/queue`): marco blanco a ancho completo,
cabeceras **PRODUCED** / **REMAINING**, columnas finales y **TOTAL** estrechas. Icono **PO CONTROL**
redibujado en estilo bloque 100×100 como el resto de la portada.

### 2026-08-20 (madrugada) — El importador de materiales usa las etiquetas del formulario

Las 21 cabeceras del CSV coinciden con los campos de
`/admin/structure/taxonomy/manage/tec_inventory/add`, incluida **Active**. **Stock level (UoS)
queda fuera a propósito**: el ECA lo inicializa a 0 y lo recalcula desde mutaciones; meterlo
en la ficha pelearía con eso. La plantilla está en `docs/plantillas/`. La prueba
`probar-la-importacion-de-materiales.php -- --de-verdad` importó los 21 campos, rechazó la
fila trampa y no dejó rastro.

### 2026-08-20 (madrugada) — Una variación creada con + Variation sale en la ficha del producto

El dueño creó *Black* para *RISE THAI KICK PAD 1 STRAP* (`/tec_product/1660`). El mensaje
de estado lo confirmaba, pero la ficha seguía vacía: solo se veía *+ Variation*. El botón
llevaba `edit[field_tec_product][widget][0][target_id]=1660`, un patrón que **no rellena**
el campo al guardar. Sin `field_tec_product`, el ECA *Insert Color entity* no mete el color
en `field_tec_color_variations` del producto, y Layout Builder no pinta nada.

La cura es la misma que `brand_id` en *+ Product*: EPP lee `product_id` de la URL, y el
enlace del botón deja de usar `edit[...]`. *+ Size* recibe el mismo tratamiento con
`color_id`. Los dos colores huérfanos (1685 y 1686) quedaron enganchados al producto 1660.

### 2026-08-19 (noche) — Un producto a medias no desaparece de la marca

El dueño creó *RISE THAI KICK PAD 1 STRAP* desde la ficha de Rise Fight Gear y Save le
devolvió a `/taxonomy/term/3410`. El producto se había guardado —el mensaje de estado lo
decía— y no había manera de verlo. El catálogo de una marca es una **lista de tallas**:
cada fila es una talla, y desde ella se sube al color y al producto. Un producto sin
color no tiene ninguna fila. Un color sin talla, tampoco. Y el botón *+ Product* de esa
pantalla llevaba pegado un `destination` a la propia marca, que pisa el redirect de ECK
—la ficha del producto, donde están *+ Variation* y *+ Size*.

Tres cosas lo tapan, y las tres hacen falta:

1. **El destination se va.** Save deja en la ficha del producto. La cadena queda
   *+ Product → Save → ficha → + Variation → + Size → la marca, con el producto ya
   dentro*. El texto de cuando la tabla está vacía deja de decir "No products in this
   brand yet", que era mentira en cuanto existía un producto sin talla.
2. **Organize se pone al lado de + Product**, el mismo par que ya tenía la pestaña
   Products del cliente. La pestaña *Organize products* sigue arriba, pero el bloque de
   pestañas del tema de la web está limitado al rol administrator: para los demás no
   existía.
3. **Lo que la tabla no puede pintar se nombra debajo.** `BrandGaps` lista los
   productos de la marca que no tienen color, o que tienen un color sin talla, el más
   nuevo primero, con un enlace a cada uno y el motivo. Un color sin tallas es un hueco
   aunque el producto tenga otros colores que sí las tienen: los precios de ese color
   no se pueden escribir en ninguna parte. Organize —que sí lista el producto pelado—
   gana una columna Sizes y marca en amarillo las mismas filas, con la misma pregunta.
   La regla vive en `BrandGaps::of()` y en ningún otro sitio.

### 2026-08-19 (noche) — Un pedido, una columna: el tablero de stock se puede leer con la cola llena

El dueño abrió `/stock` con diez pedidos en la cola y dijo lo que había que decir: *«hay muchos
elementos descuadrados en la pantalla, no entiendo nada»*. Y la tabla **no estaba partida**: 27
celdas arriba y 27 en cada fila. Por eso no la había cazado nada. Todas las pruebas de esta
pantalla preguntan «¿carga?», y cargaba.

**Lo que estaba mal era el emparejado.** Cada pedido ocupaba **dos columnas**, la casilla en una y
la cantidad en otra. La cantidad iba pegada al borde derecho de la suya, así que un número corto
—un `0`, un `30`— acababa a un dedo de la casilla del pedido **siguiente** y a tres dedos de la
suya. Se leía `30 ☐` mientras la cabecera decía `☐ 26-001`. Había una raya de 2px separando los
grupos y no sirvió de nada: **la cercanía gana a cualquier raya**.

Ahora la casilla y la cantidad van en la **misma celda y pegadas**, con el hueco a la izquierda.
Lo que separa a un pedido del siguiente es aire, y lo que junta a una casilla con su cantidad es
que se tocan. La cabecera de cada columna lleva el número del pedido en una línea y la casilla que
marca la columna entera en la siguiente, justo encima de la vertical de las casillas de abajo,
porque detrás lleva un hueco que mide lo mismo que una cantidad. Esas dos medidas se declaran
juntas en la hoja de estilos a propósito, y la prueba comprueba que siguen juntas: si alguien
cambia una y no la otra, la cabecera se descoloca de su columna sin que nada falle.

**Diez pedidos cuestan ahora diez columnas y no veinte**, que es de lo que iba el encargo: el
tablero tiene que aguantar que la cola crezca, y la cola es lo único de esta pantalla que no
decide nadie.

Y **cada columna tiene su raya vertical**, por lo mismo que la tiene una hoja de cálculo: con
veinte columnas en pantalla, una columna cuyo borde no se ve es una columna que no se puede seguir
hacia abajo.

**La columna `Ordered (UoP)` era un segundo fallo en la misma pantalla**, y el dueño tuvo que
señalarla dos veces, la segunda con un círculo naranja encima. La celda lleva la cifra y además un
enlace por cada pedido de compra que espera, y esos enlaces iban **al lado** de la cifra. De ahí
salían dos cosas: la celda está alineada a la derecha, así que un `45 PO PO PO` dejaba el `45` en
el extremo izquierdo con la cabecera encima del último enlace; y un material esperando seis pedidos
hacía que esa sola celda ocupase más que tres columnas.

El primer intento fue recortar la celda con un `max-width`, y **lo empeoró**, que es lo que hay que
apuntar: la tabla se dimensiona a `max-content`, o sea que una columna se cobra siempre el ancho que
le pide su contenido, y un `max-width` no recorta nada más que la caja —el texto sigue saliendo por
encima de las dos columnas de su derecha—. Estaba escribiendo `-561.99` encima de `636.99`. Ahora la
medida vive en un bloque **dentro** de la celda, que es contra lo que la columna se mide, así que
los enlaces bajan de línea en vez de salirse; y van **debajo** de la cifra, con lo que la cifra
vuelve a quedar bajo su propia cabecera.

Hubo que deshacer un truco de Bootstrap dentro de la celda: `.form-check` reserva 1.5em de relleno
a la izquierda y mete el `input` ahí con un margen negativo y un `float`, que dentro de una fila
flexible vuelve a abrir justo el hueco que este cambio cierra.

**Y los pedidos de prueba se quedan.** Se propuso vaciar la cola y el dueño lo paró en seco, con
razón: *«precisamente para esto están, para que falle aquí antes de meter el ERP en el servidor y
que los empleados no puedan trabajar porque está todo lleno de fallos.»* Son diez columnas de
mentira que hacen visible aquí un fallo que allí lo habría visto Lukpla.

- No hay receta: el tablero es código, así que con el `git pull` va.
- Prueba: `scripts/el-tablero-de-stock-se-lee-con-muchos-pedidos.php`, que lee el tablero **de
  verdad**, sin montar nada, con lo que haya en la cola. No mira píxeles, que no se pueden mirar
  desde un guion: mira la regla —una columna por pedido, una casilla y una cantidad por celda, las
  dos del mismo pedido y la casilla delante— y se niega a aceptar las clases viejas, de modo que
  volver a separar la pareja falla en voz alta. Y se niega también a un `max-width` en la celda de
  `Ordered`, que es el arreglo que parecía bueno y no lo era. Dos de sus medidas las lee de la hoja
  de estilos y no del HTML, porque son casualidades que sostienen la pantalla: el hueco de la
  cabecera tiene que seguir midiendo lo que una cantidad, y el ancho del bloque de enlaces tiene que
  seguir estando en el bloque.
- **Lección para la próxima tabla ancha:** en una tabla dimensionada a `max-content`, la manera de
  limitar una columna es poner la medida en un bloque de dentro. Un `max-width` en la celda no
  limita, esconde.
- Está contado en el README de `tec_production`, sección *One column per order on the stock board*.

### 2026-08-19 (noche) — La marca manda en el orden de sus productos, y la proforma nace ordenada en cuatro niveles

Esto es lo que se ganaba de verdad con quitarle el cliente al producto, y lo que estaba apuntado
desde el 19 de agosto por la mañana: **hoy las proformas no tenían ningún orden**. Salían como
las devolviera MySQL, o sea por número interno de talla, que no significa nada para nadie.

El dueño eligió las cuatro decisiones, una por una: se arrastra **como en *Organize products***,
la pantalla vieja **se retira**, se empieza **por orden alfabético** y ya arrastra él, y el orden
**se congela** al crear las líneas, como los precios.

**Lo primero que hubo que corregir era una suposición mía.** Escribí que los colores no tenían
ningún orden, y el dueño mandó una foto de la ficha de un producto con los tiradores de arrastrar
marcados en rojo. Tenía razón: los colores se arrastran en el formulario del producto desde que
existe, y las tallas llevan un peso deliberado del vocabulario desde
`scripts/poner-las-tallas-en-su-orden.php`. Así que de los cuatro niveles **tres ya existían y
nadie los usaba**, y lo que faltaba era justo el del medio:

| Nivel | De dónde sale |
| :-- | :-- |
| Marca | el orden de la lista de marcas de la ficha del cliente |
| Producto | `field_tec_catalogue_position`, arrastrado en la ficha de la marca |
| Color | el orden de la lista de colores de la ficha del producto |
| Talla | el peso del término en el vocabulario `tec_sizes` |

**Por qué el orden de los productos no podía seguir donde estaba.** Era del *cliente*: se
arrastraba en `/tec_crm/{id}/reorder` y lo guardaba *draggableviews*, que pega a su clave la
vista, la pantalla **y los argumentos**. Ocho clientes eran ocho ordenes del mismo catálogo, y
ninguno le servía a la proforma: la vista que crea las líneas corre para *un* cliente y no puede
leer el orden de otro, y el catálogo de una marca no tiene ningún cliente a quien preguntar. De
esos mismos pesos tiraban las pantallas de líneas, enganchando el número de una *línea* contra el
de un *producto* —el fallo que se arregló esta misma mañana—. Un orden que solo servía en la
pantalla donde se arrastraba.

Ahora el número vive en el producto: `field_tec_catalogue_position`, escondido en el formulario y
en la ficha porque no se teclea, se arrastra. Un producto nuevo recibe **el último sitio de su
marca**, que un producto sin sitio se colaría por delante de todo lo que alguien ordenó a
propósito; y un duplicado recibe uno nuevo, porque llega con el de su original y dos productos no
pueden reclamar la misma fila.

**La pantalla es una pestaña de la marca**, *Organize products*, en
`/taxonomy/term/{tid}/organize`. Arrastrar del núcleo, la foto del primer color, cuántos colores
lleva cada producto, y los números de fila se renumeran solos mientras se arrastra. Quien puede
entrar es quien puede **editar la marca**: cambiar lo que ven todos los clientes es el mismo
trabajo que editar la marca, y de paso eso es lo que mantiene la pestaña fuera de los otros
vocabularios, porque Drupal esconde una pestaña cuya ruta no te deja abrir. En la pestaña
*Products* de la ficha de un cliente, que agrupa por marca, cada cabecera de marca lleva un botón
**Organize** que vuelve al cliente al guardar: el orden es de la marca, pero quien quiere
cambiarlo suele estar mirando un cliente.

**El número se escribe directamente en la tabla de su campo**, y es el único sitio del módulo que
se salta la API de entidades. Guardar un producto despierta *«TEC Product: Set references on
sub-entities»*, que recorre y vuelve a guardar cada color y cada talla suyos: entre 0,2 y 1,1
segundos por producto y dos líneas de registro cada vez, medido. Arrastrar una marca de veinte
productos serían veinte segundos y cuarenta líneas de registro para un número que ningún
automatismo lee. Escrito a pelo son 0,005 segundos, más invalidar las etiquetas de caché a mano,
que es la parte que el guardado sí hacía. Vale aquí y en ningún otro sitio: de este número no se
calcula nada y ninguna ECA lo escucha.

**La proforma se ordena en PHP, después de la consulta**, y esto es una decisión y no un atajo.
Dos de los cuatro niveles son *la posición de un valor dentro de una lista*, y para leerla Views
tendría que enganchar la tabla del campo por segunda vez; ese enganche no va atado a la fila, así
que cada fila vuelve una vez por cada valor de la lista —medido en la pestaña del cliente: tres
productos, seis filas—. Se ordena en `hook_views_post_execute()` y no en `pre_render()` porque
**la pantalla que más importa no se dibuja nunca**: el botón *+ Order* le da la vista a ECA, que
la ejecuta y va creando una línea por fila. Ordenar al volver la consulta es el último punto que
los dos caminos comparten.

**Y en cuanto las líneas existen, el orden se congela.** Es lo que pidió el dueño en una frase, y
sale gratis: el orden pasa a ser la sucesión de los números de las líneas, y las siete pantallas
que listan líneas ordenan por ese número. Volver a arrastrar una marca el mes que viene cambia la
proforma siguiente, no las ya emitidas. La misma regla que los precios.

**La trampa que saltó por el camino, y que merece quedar escrita.** Al poner el orden por número
de línea en la pantalla `default`, las que **agregan** lo heredaron, y Views mete en el `GROUP BY`
todo aquello por lo que ordena: el bloque de totales pasó a devolver *una fila por línea*, cada
una con su propio total. Se arregló poniendo el orden en cada pantalla que lista líneas y
dejando `default` y las seis que suman **sin ninguno**, explícitamente. El guardián vigila las dos
mitades, porque son contrarias y quien arregle una puede romper la otra sin enterarse.

**Lo que se ha retirado:** la pantalla `/tec_crm/{id}/reorder` (`tec_products:page_2`), los
botones que llevaban a ella y **todas** las filas de pesos de `draggableviews`. Ninguna vista
menciona ya ese módulo.

En local esto se nota poco, y conviene decirlo: aquí solo hay **dos productos con marca**, así que
sembrar el orden alfabético fue un trámite. Donde se va a ver es en el servidor, con el catálogo
de verdad, y por eso las tres recetas tienen que pasarse allí al desplegar.

Recetas: `scripts/la-marca-ordena-sus-productos.php` (el campo y la siembra, con orden natural
para que *Glove 2* vaya antes de *Glove 10*), `scripts/las-pantallas-siguen-el-orden-de-la-marca.php`
(las vistas, los botones y la retirada de la pantalla vieja) y `scripts/la-proforma-nace-ordenada.php`
(el orden de las líneas). Las tres se pueden pasar dos veces.

Prueba `scripts/la-marca-manda-en-el-orden.php`, **30 comprobaciones**, montada con una regla:
los nombres, los colores y las tallas están elegidos para que el orden correcto y el alfabético
**no coincidan en ninguno de los cuatro niveles**, porque un orden que resulta ser el alfabético
no prueba nada aquí —el alfabético es lo que hacía el ERP antes—. Arrastra por el formulario de
verdad, pulsa la bandera de verdad, y luego vuelve a arrastrar la marca para comprobar que el
pedido emitido no se mueve y el siguiente sí. Guardián **210 de 210**, con la sección 25 nueva.

### 2026-08-19 (última hora) — El pie del pedido se mete dentro de la tabla, que es el único sitio donde una cifra cae bajo su columna

El dueño miró la suma de piezas que se le había puesto esta misma mañana y dijo lo que faltaba por decir:
**«Qty Total tiene que ir debajo de qty»**. Tenía razón y no era una manía: una cifra suelta al borde
derecho obliga a buscar de qué columna es, y la respuesta correcta es que se vea sin buscarla.

No se podía desde donde estaba. Aquel pie era `attachment_1`, otra pantalla de la misma vista enganchada
detrás de la tabla, es decir **una segunda tabla**. Dos tablas no se ponen de acuerdo en el ancho de sus
columnas: el navegador mide cada una por lo que lleva dentro, así que una cifra de la de abajo cae bajo
una columna de la de arriba **por casualidad**, y deja de caer en cuanto un nombre de producto es más
largo. Con CSS no se arregla, y la que se intente hoy se rompe el día que un cliente tenga un producto
con el nombre largo. El único sitio donde una cifra comparte anchos con una columna es **dentro de la
misma tabla**.

Así que el pie es ahora **una fila más de la tabla**, escrita en el servidor:
`modules/custom/tec_production/src/OrderFoot.php`, colgada de
`hook_preprocess_views_view_table()`. Las piezas debajo de Qty, el dinero debajo de Item total, y el
`Total` entre las dos, que es exactamente la forma que el borrador ya dibujaba en el navegador. Las dos
pantallas del ERP que suman piezas se parecen por fin.

**En el servidor y no en el navegador**, porque una de las dos pantallas es la Pro Forma que se imprime y
se le da a un cliente, y un papel no puede depender de que un guion haya corrido. El borrador es el caso
contrario y por eso sigue sumando en el navegador: allí las cifras se mueven a cada tecla.

**La columna se busca por su nombre y jamás contando**, que es la regla que el guion del borrador ya
seguía: Views bautiza cada celda con el nombre del campo que la llena. La cantidad es la sexta columna de
ocho en la ficha del pedido y la tercera de ocho en la Pro Forma, y las dos se mueven el día que alguien
añada una columna. La prueba lo comprueba como hay que comprobarlo: dibuja las dos pantallas y calcula en
qué columna **empieza** cada celda del pie sumando los `colspan` de las anteriores. Un número una columna
más allá no da ningún error; solo se lee mal.

**Y con el `attachment_1` se fue el último campo agregado de esa vista**, y con él la trampa que traía.
Cuando Views agrega un campo lo dibuja otro manejador que lee `separator` como separador de millares, y
por eso el total decía `฿44, 090.00`. Esta mañana se arregló poniendo comas en los cuatro; esta tarde dos
de ellos han dejado de existir y su cifra la escribe `number_format()`, igual que la del pie de compras y
la del borrador. Los tres pies del ERP escriben ya un millar de la misma manera.

**Lo que no se toca.** Compras conserva su pie de tres líneas —Subtotal, VAT, Total—, que también es una
tablita por debajo: allí no hay ninguna cifra que deba caer bajo una columna, son tres importes y el
importe va al final en las dos tablas. Queda apuntado que el truco de hoy sirve para ellos el día que
haga falta.

Un detalle que enseñó la prueba y que no es un fallo: **un pedido en el que ninguna línea lleva cantidad
no tiene pie**, porque la pantalla filtra las líneas a cero y una tabla sin filas no tiene nada que
sumar.

Receta `scripts/el-pie-del-pedido-se-mete-en-la-tabla.php` —solo retira la pantalla que sobraba; la fila
es código— y prueba `scripts/se-cuentan-las-piezas-del-pedido.php`, reescrita para mirar columnas en vez
de leer texto. Guardián **201 de 201**.

### 2026-08-19 (última hora) — El nombre que Óscar le puso al pedido de un clic se va del ERP, y cuatro piezas se quedan a medio camino

El dueño pinchó «+ Orders» en la ficha de un cliente, miró a dónde llevaba y dijo lo que había que decir:

```
/flag/flag/tec_draft_order_excel_lover/121?destination=/customer/121
```

*«No me gusta nada ese nombre de excel lovers, lo puso Óscar sin yo saberlo y me parece que no es
profesional.»* En pantalla no se veía —el botón dice «+ Order» y la bandera se llama «TEC Order: Draft
Sales order»—, pero **una dirección se ve al pasar el ratón, se copia en un correo y se pega en un
mensaje**, y ahí un nombre así deja de ser una broma interna. Preguntado hasta dónde llegar, el dueño
eligió *todo*.

El nombre nuevo ya estaba escrito en el hermano: la bandera de compras es `tec_order_draft_po`, así que
la de ventas es **`tec_order_draft_sales`**. Lo demás sigue la misma regla, decir lo que la cosa hace.

| Antes | Ahora |
| :-- | :-- |
| `tec_draft_order_excel_lover` | `tec_order_draft_sales` |
| `tec_order_checkout_excel` | `tec_order_checkout` |
| `excel_lover_button` | `sales_order_button` |
| `tec_excel_lover_orders` | `tec_order_draft_screens` |
| `tec_order_eca_create_draft_excel_lover` | `tec_order_eca_create_draft_sales` |
| `tec_order_eca_create_draft_po_excel_lover` | `tec_order_eca_create_draft_purchase` |
| `tec_excel_lover_form` | `sales_order_draft_form_unused` |

Y con ellos dieciocho etiquetas de las que lee una persona, más el PHP, el README de `tec_production`,
`docs/customer-portal.md` y una prueba que hasta hoy se llamaba `el-excel-lover-mira-las-marcas.php`.

**En Drupal el nombre de una entidad de configuración no se cambia**: se crea otra igual con el nombre
nuevo y se borra la vieja. Entre esas dos cosas hay que mover todo lo que la citaba, que aquí eran **110
sitios**: los permisos de cuatro papeles, las acciones de las banderas, el campo de la bandera en seis
fichas de contacto, la pieza de Layout Builder de una séptima, dos vistas de pedidos, tres procesos de
ECA, ocho ajustes de campo y una regla de CSS. El orden es crear, mover y borrar, y es el orden y no
otro: al revés hay un instante en que cuatro papeles tienen permiso sobre una bandera que no existe.

**La mina de ECA, otra vez.** Un proceso vive en dos ficheros —`eca.eca.X` es lo que se ejecuta y
`eca.model.X` es el dibujo que ve el editor— y **el editor regenera el primero desde el segundo cada vez
que alguien pulsa guardar**. Cambiar solo el ejecutable habría dejado el nombre viejo escondido en el
dibujo, esperando a que alguien abriera el editor para devolverlo. Así que el nombre entra en los dos, y
después se hace la pregunta que importa: se le entrega el dibujo al modeller, que regenera el
ejecutable, y se comprueba que **no cambia ni un valor**. Con red: si cambiara alguno, el guion devuelve
el ejecutable a como estaba y lo cuenta, en vez de dejarlo regenerado a medias. Los tres pasaron.

Esa comprobación va **al final y no al principio**, y eso se aprendió a la primera: el modeller valida
cada valor contra lo que existe, y una bandera o una vista que todavía no se ha creado no es un valor
permitido. Preguntarle antes de crear las cosas nuevas es que diga que no a todo.

**Y las cuatro piezas que se quedaron a medias**, que es lo que ha enseñado algo. Las dos acciones de
cada bandera —marcar y desmarcar— no las escribe nadie: las crea el módulo Flag cuando nace la bandera y
las borra cuando la bandera se va, **buscándolas por su identificador**. Aquí no las encontró, porque el
nombre nuevo ya había entrado *dentro* de ellas con todo lo demás: por dentro decían
`flag_action.tec_order_draft_sales_flag` mientras el objeto seguía llamándose `...excel_lover_flag`.
Cuatro objetos que Drupal busca por el nombre y en los que encuentra a un desconocido, conviviendo con
los cuatro buenos que el módulo sí creó al nacer las banderas nuevas.

Eran invisibles para todo lo que había montado. **Cargar una acción por su identificador no llega a
ellas**: el almacén de configuración indexa lo que lee por el `id` que hay *dentro* del fichero, no por
el nombre del fichero, así que pedir `flag_action.tec_draft_order_excel_lover_flag` devuelve nada aunque
el fichero esté ahí. El guion preguntaba así y decía que estaban borradas. El guardián preguntaba así y
daba 200 de 200. Aparecieron en la lista del `drush cex`: cuatro **Update** con nombre viejo, al lado de
los cuatro **Create** con nombre nuevo.

La regla que ahora las caza vale para toda esa familia: **un objeto cuyo nombre acaba en una cosa y cuyo
`id` dice otra es un resto**, y se borra. Si por dentro dijera lo mismo que su nombre no sería un resto
sino algo sin mover, y eso se cuenta en vez de borrarlo, que es la diferencia entre limpiar y romper. Y
los dos barridos, el del guion y el del guardián, leen ya **el nombre de cada objeto** y no solo lo que
lleva dentro. El barrido que solo mira dentro es exactamente el que dejó pasar esto.

**Lo que no se toca, a propósito.** Este diario, que cuenta lo que pasó el día que pasó y con los
nombres de ese día: reescribirlo sería mentir sobre el pasado. Los guiones de limpieza de una vez, que
son la lista de la compra de una limpieza ya hecha. Y las direcciones `/o/draft/N` y `/po/draft/N`, que
nunca llevaron el nombre y son las que la gente tiene en el historial del navegador. Sí se ha tocado una
línea de las secciones de arriba, la del paso 3 del plan de marcas, porque esas secciones señalan cosas
que existen hoy y ahora señala la vista por lo que hace.

La receta es `scripts/se-va-el-excel-lover.php` y se puede lanzar dos veces: la segunda no encuentra
nada que cambiar. Guardián **200 de 200**, humo de 60 páginas, y `drush cex` que ya solo tiene nombres
que se pueden enseñar.

### 2026-08-19 (última hora) — El pedido dice cuántas piezas lleva, guardado y en borrador, y la casilla del precio se pone en la línea de su fila

Tres cosas pequeñas que el dueño pidió seguidas, y una de ellas destapó una cifra mal escrita que
llevaba ahí desde siempre.

**El pedido guardado suma sus cantidades**, en la misma línea que el Grand total. Es un campo agregado
—`field_tec_quantity` con `group_type: sum`— en el `attachment_1` de la vista de líneas, que es el pie
que ya dibujaba los importes. Y ese pie es **el mismo que alimenta la impresión de la Pro Forma**, así
que la cifra sale también en el papel; se preguntó antes de hacerlo y el dueño dijo que sí. Va **solo en
ventas**: el pie de compras tiene sus tres líneas —Subtotal, VAT, Total—, su bloque no agrega, y el
dueño fue claro, *«en compras no hay suma de quantities»*.

**Y de paso, los millares.** Cuando Views agrega un campo lo dibuja *otro* manejador, y ese lee
`separator` como **separador de millares**. Estaba en coma y espacio, así que el pie de un pedido de
cuarenta y cuatro mil bahts decía `฿44, 090.00`, con un hueco en medio del número. Los cuatro campos
agregados de esa vista llevan ya coma y nada más. Nadie lo había mirado porque es la línea del total, la
que se lee de reojo.

**El borrador cuenta las piezas mientras se teclean.** Ese pie no lo puede dibujar el servidor, porque
las cifras se mueven a cada tecla, así que lo monta el navegador. Ahora suma las cantidades y pone el
número **debajo de su columna**, y para eso busca la columna **por su nombre y no por su número**: la
fila del pie se montaba a ciegas, con una cuenta fija de celdas vacías, y añadir o mover una columna
habría deslizado el total bajo el título de al lado. Ventas tiene nueve columnas con la cantidad en una
de ellas; compras tiene siete y ninguna de cantidad, así que allí el pie sigue con sus tres líneas y no
cuenta nada.

**Y la casilla del precio, en la línea de su fila.** En el catálogo de una marca todas las celdas llevan
una línea de texto menos esa, que lleva un formulario, y Drupal envuelve cada casilla en un `.form-item`
con margen arriba y abajo —sitio para una etiqueta que aquí está escondida—. El margen de arriba era lo
que empujaba la casilla por debajo de la línea de su fila, y el de abajo lo que hacía la fila más alta
que nada de lo que tiene dentro. Dos reglas: los márgenes a cero y las celdas centradas. **Hacen falta
las dos**; centrar a secas dejaría la casilla colgando medio margen por encima.

### 2026-08-19 (última hora) — Un pedido se gana su número la primera vez que alguien le da a guardar

Quedó escrito ayer que si lo que molestaban eran los huecos en la numeración, el arreglo no era devolver
números sino no gastarlos hasta que el pedido fuera de verdad. El dueño puso el momento exacto, y no era
el que se había supuesto: **«el pedido se gana el número cuando se le da por primera vez a save»**.

Hasta hoy se numeraba al nacer, y nacer es un clic en «+ Order» en la ficha de un contacto, que monta
una lista de la compra: una línea vacía por cada talla que ese cliente puede comprar. **Muchos de esos
clics son una mirada, no un pedido**, y los que nadie rellenó se pueden contar: de los treinta y seis
pedidos que había en el sitio el día del cambio, **dieciséis no tenían ni una cantidad escrita**, y cada
uno se había quedado con un número de la serie de su contacto para siempre, porque un número repartido
no vuelve. El contador de KJ iba por el 24 con diez de esos números vacíos.

**Guardar, y no un botón de confirmar.** Hay un Checkout en cada pedido y un Accounting verified, y
nadie ha pulsado nunca ninguno de los dos: los veintinueve pedidos de compra estaban sin estado y los
siete de venta seguían en Open, así que una regla que esperase a eso no habría numerado nada. La otra
candidata era la primera cantidad escrita, y es demasiado lista: bautiza el pedido a espaldas del dueño
mientras teclea, y en cambio se niega a bautizar el que guarda vacío a propósito para seguir mañana.
**Pulsar Guardar es el acto que dice «esto se queda»**, que es justo lo que significa un número.

Por eso quién llama es todo el diseño, y **no es un gancho de guardado**. El código guarda pedidos todo
el día por sus motivos —una ECA que pega el contacto, un estado que cambia, una línea que se cuenta— y
nada de eso es una persona quedándose con un pedido. Cuelga de los botones de Guardar que sí pueden
significarlo: las pantallas de borrador `/o/draft/N` y `/po/draft/N`, reconocidas **por su dirección**,
porque «o/draft» dice qué pantalla es esta y «page_2» no; el formulario del propio pedido, que es la
ruta rara al mismo acto y del que se deja fuera el de borrar, porque borrar no es quedarse; y el botón
de la lista de la compra, que crea los pedidos y se los queda de una vez.

Los dos primeros se enganchan distinto porque **Drupal saca los manejadores de dos sitios**: el
formulario de una vista deja el botón desnudo y corre la lista del formulario, y el Guardar de un
formulario de entidad trae la suya —`::submitForm` y `::save`—, y la del botón gana a la del formulario.
Y hay un detalle que costó encontrar: apuntarse desde el `hook_form_alter` pone el manejador **por
delante** del que guarda las líneas, porque `views_entity_form_field` añade el suyo desde un `#process`
y el procesado ocurre después de que todos los módulos hayan hablado. `#after_build` es el primer sitio
desde el que el final de la lista es de verdad el final.

Que vaya el último importa por dos motivos. Un número gastado en un guardado que luego se cae sería un
hueco en la serie de un cliente, o sea lo mismo que se está evitando. Y el orden dice qué significa
Guardar: **primero se queda lo que el dueño escribió, y el pedido se hace real por eso**.

Mientras tanto el pedido necesita llamarse de alguna manera, así que el gancho de guardado sigue ahí,
pero dando **nombre y no número**: «Draft order for Kajenta», copiando la redacción de las dos ECA hasta
en la mayúscula rara de «Draft Purchase order», para que la lista de pedidos no enseñe dos maneras de
escribir lo mismo. Sin eso, un pedido creado por código con el título vacío se quedaría en la lista como
un renglón en blanco que no se puede ni pinchar. Y la forma de un número —código, año, tres cifras— vive
en un solo sitio, `OrderNumber::isNumber()`, para no escribirla dos veces: un título tecleado a mano que
acabe en año y tres cifras se lee como numerado, y ese es el lado seguro del error, porque lo peor que
hace es dejar un nombre en paz.

**El guardián ha tenido que aprender la regla nueva.** Un pedido sin número ya no es un fallo: es un
borrador que nadie ha guardado todavía, y se cuenta como cifra, no como alarma. Lo que sí es un fallo es
un pedido **sin número y con cantidades escritas**, porque las cantidades solo llegan a la base de datos
cuando alguien pulsa Guardar en la pantalla del borrador, que es justo el botón que tiene que numerar.
Si hay cantidades y no hay número, el gancho no ha corrido.

### 2026-08-19 (cierre) — Tres cosas que no daban error: una cantidad en negativo, un papel que no se parecía a su pantalla y una foto que ya no crecía

El dueño trajo seis asuntos y pidió tres: **el 5, el 6 y el 2**. Los tres tenían en común lo que los hacía
molestos: ninguno daba error. Un pedido con menos diez pares se guardaba, un documento de producción se
imprimía con los productos en otro orden, y una foto se quedaba quieta. Nada en los registros, nada en rojo.

**El 5: una cantidad no es negativa.** Los tres campos de cantidad de una línea —la de venta, la de compra y
lo recibido— nacieron con `min: null`, o sea sin suelo, y un campo entero sin suelo acepta el signo menos
igual que acepta el diez. Ahora los tres dicen **mínimo cero**, y eso lo hace cumplir el navegador (el widget
saca su `min` de la definición del campo) y el servidor (Drupal le cuelga al campo una restricción de rango
que se mira en cada guardado, venga de un formulario o de código). Se probaron las dos puertas por separado,
porque cerrar solo la del navegador es dejarla cerrada con pestillo por fuera.

**El suelo es cero y no uno, a propósito.** En `/o/draft` y `/po/draft` el cero es *cómo se quita un renglón*:
se escribe 0 y la vista deja de mostrar la línea, porque filtra por cantidad distinta de cero. Con `min: 1` se
arreglaba el signo y se rompía la única manera de deshacer.

Lo que hacía un negativo aguas abajo era peor que aceptarlo. **El total de la línea se quedaba vacío**, porque
la ECA que multiplica cantidad por precio tiene una rama que borra el total cuando la cantidad es menor que
1 —pensada para el cero, que ahí significa «esta línea no va»— y el menos diez caía en esa rama. Pero **el
documento de producción sí lo sumaba**: mandaba al taller cortar diez pares menos de una talla. Un dato
imposible se paraba en la pantalla del dinero y seguía hasta el papel del taller.

Lo ya escrito no lo repasa nadie: cambiar el mínimo no toca lo guardado. Queda **una línea con −10** en
`PRUEBA 26-006`, y la próxima vez que alguien la guarde el ERP se la parará, que es exactamente lo que se
quería. El guardián la enseña como cifra y no como alarma: son datos del dueño, no código roto.

**El 6: el papel dice lo mismo que la pantalla, y son dos mitades.** La de arriba, en el bloque: ordenaba los
productos con un `uasort` **por su nombre**. Buen orden para un catálogo y el equivocado para un papel que se
lee al lado del pedido del que salió; PRUEBA Producto, añadido al final, salía primero. Ahora cada producto se
coloca **por la primera línea que lo menciona**, y cada color igual, así que un producto pedido otra vez más
abajo se queda donde apareció en vez de bajarse al final. Las tallas siguen yendo por el peso de su
vocabulario y eso es correcto: el documento lista todas las del catálogo, incluidas las que van a cero, y esas
nunca estuvieron en una línea para tener posición.

La mitad de abajo era la interesante. Cuatro pantallas de la vista de líneas —la pestaña *Line items*, el
bloque editable y las dos de `/o/draft`— ordenaban por la tabla de pesos de `draggableviews`, apuntando a
`tec_products:page_2`, que es el orden que el dueño arrastra en la pantalla de productos de un cliente. La
consulta que salía de ahí enganchaba **el número de una línea contra el número de un producto**:

```sql
LEFT JOIN draggableviews_structure ON tec_line_item_field_data.id = draggableviews_structure.entity_id
ORDER BY COALESCE(weight, -9223372036854775808)
```

El módulo de arrastrar no sabe ordenar por una entidad que no sea la de la fila: engancha siempre por la clave
de la tabla base. La relación `field_tec_product` que la ordenación tenía puesta no la miraba nadie.

**Y lo peor de un error así es que casi siempre acierta.** Las líneas nuevas van por el 4600, en la tabla de
pesos no hay ningún producto con ese número, ninguna fila encuentra peso, todas empatan a menos infinito y
MySQL las devuelve como las lee, que es por su número: el orden bueno, por casualidad. Donde muerde es en los
pedidos viejos, y se puede señalar con el dedo: la línea **195** de un pedido de hace meses sí encuentra un
producto 195 en la tabla, se le pega su peso y esa línea salta al final de su propio pedido. Un orden que solo
falla con los datos antiguos es un orden que nadie mira hasta que ya no se acuerda de por qué. Ahora las
cuatro ordenan **por el número de la línea**, y al guardar la vista se cayó sola la dependencia del módulo de
arrastrar: no la usaba para nada.

Las dos mitades usan **la misma clave** —el número de la línea— y tienen que seguir usándola o volverán a
discrepar en silencio. La prueba las mira juntas: monta un pedido con ZORRO, MEDIO y ABETO en ese orden, que
es el contrario del alfabeto, y comprueba que el documento y la pantalla dicen lo mismo. Con tres nombres al
revés no puede acertar por casualidad, que es lo único que hace que una prueba de orden sirva de algo.

**El 2: la foto crece otra vez, y la culpa estaba escrita en el módulo.** El javascript de Simple Popup Views
es esto:

```js
$(".spv_on_hover").hover(...)
```

Se ejecuta una vez, en el instante en que se lee el fichero, **sin `document.ready` y sin `Drupal.behaviors`**,
y se engancha a las filas que existan justo entonces. Todas las pantallas de este ERP que llevan la foto
llegan después de ese instante: las pestañas del pedido son quicktabs, la parrilla del log viene por ajax y
BigPipe manda el contenido principal después de los scripts. El enganche no encontraba ninguna fila, no daba
ningún error, y la foto se quedaba quieta.

Un hover que abre algo que ya está en la página es un hover que el CSS hace desde 1998, así que el arreglo es
**una regla**: `.spv-popup-wrapper:hover .spv-popup-content { display: block }`. Sin binding, sin timing, y
funciona para marcado que llegue dentro de una hora. Gana por especificidad al `display: none` del propio
módulo, así que no depende de qué hoja se agregue antes.

**Dónde se engancha es la mitad de la decisión.** No en una hoja nuestra atada a una dirección o a una
pantalla, sino **dentro de la biblioteca del propio módulo**, con `hook_library_info_alter()`. El módulo cuelga
esa biblioteca de todas las páginas y el campo está en **diecisiete pantallas de ocho vistas**: donde puede
dibujarse la foto, su hoja ya está. Así la regla no puede acabar donde no hay marcado ni faltar donde sí lo
hay. Y se ha quitado la copia que vivía desde junio encerrada en `.tec-log__table`, donde arreglaba una
pantalla de las diecisiete: dos sitios reclamando el mismo comportamiento es lo que hace que el siguiente que
lo lea no sepa cuál manda. El javascript del módulo se deja en paz —su disparo por clic tiene el mismo fallo,
pero ninguna pantalla usa clic, y un parche a un contrib hay que mantenerlo al día para siempre.

La prueba de esto le pregunta al marcado **lo mismo que le pregunta la regla**, con XPath: ¿hay una foto
grande colgando del mismo cajón al que se le pasa el ratón? Buscar dos trozos de texto y suponer que uno está
dentro del otro es el error que ya se cometió una vez esta semana, comparando html dibujado en vez de datos.

**Y una cosa que se vio de paso, sobre el 4 que el dueño está pensando.** Se dijo aquí que el número de un
pedido era su id y que por eso un borrado «quema» el número. **No es su id**: es `OrderNumber`, con un
contador guardado por contacto y año. Y que un número borrado no vuelva **no es un fallo, es una decisión del
17 de agosto** con su entrada en este diario: antes se deducía contando pedidos, se borraba el último, la
cuenta bajaba y el siguiente nacía con un número que el proveedor ya tenía impreso. Si lo que molesta son los
huecos, el arreglo no es devolver números, es **no gastarlos hasta que el pedido se confirma** —y el propio
`OrderNumber` ya lo tiene previsto, porque el bundle de borrador está fuera de su lista a propósito.

Guardián **192 de 192** (seis centinelas nuevos: los tres suelos, el orden en el bloque, el orden en las
cuatro pantallas y la regla del ratón). Prueba de humo, 61 páginas. Tres pruebas nuevas, cada una con su
pedido de mentira que se recoge al acabar, contadores devueltos incluidos.

### 2026-08-19 (cierre) — Un rediseño pedido y desdicho en veinte minutos: del catálogo solo queda la casilla del precio, un 60% más corta

El dueño miró el catálogo de la marca y dijo *«la lógica está bien, pero el diseño en pantalla es horrible;
ponlo bonito»*. Se puso bonito: cabecera en versalitas, filas sin rayas, foto enmarcada, el dinero con cifras
de ancho fijo, y sobre todo **cada cosa dicha una vez** —el nombre del producto al empezar el producto, la foto
y el color al empezar cada color, los bloques separados con una raya— para que seis filas que se leían iguales
dejaran de esconder lo único que cambiaba, que es talla, precio y coste.

**Y al verlo, la respuesta fue *«no no, déjalo como estaba antes; simplemente reduce la caja de precio un
60%»*.** Así que se ha ido todo menos eso. Queda una hoja de estilo de nueve líneas con una sola idea: la
casilla del precio mide **5.25rem, 84 píxeles**, el 40% de los 210 que ocupaba —el ancho entero de su
columna— cuando lo único que se teclea ahí son cuatro dígitos y dos decimales. El resto de la tabla lo dibuja
el tema como dibuja las demás tablas del ERP, con sus rayas y sus repeticiones.

**Lo que sí se ha mantenido de la manera de engancharla.** El ancho corto no se ha añadido a
`asset_injector.css.tec_excel_lover_orders` —donde vive el mismo apaño para la casilla de cantidad de
`/o/draft` y `/po/draft`— porque ese inyector se ata a direcciones, y el catálogo va embebido en la ficha del
término y mañana puede ir en otro sitio. Se engancha **a la pantalla**: `hook_views_pre_render()` mira si la
vista es `tec_products:brand_catalogue` y le cuelga la biblioteca. Si el catálogo se muda, el estilo se muda
con él.

**La lección del rato perdido no es sobre CSS.** «Ponlo bonito» se leyó como permiso para rediseñar, y lo que
había detrás era una queja concreta sobre un elemento concreto —la casilla— dicha con el número al lado: un
65% primero, un 60% después. Cuando el encargo trae una cifra, la cifra *es* el encargo; lo demás son ideas
propias colándose de gratis, y aquí costaron un borrado. La próxima vez, la cifra primero y lo bonito
preguntando.

También se va con el rediseño el fallo más interesante del día, que ya no tiene código donde vivir: la
primera versión del «cada cosa una vez» decidía si algo era repetición comparando el html dibujado de la
celda, y la columna de la foto no es texto sino un árbol de render, así que todas las filas comparaban iguales
como la palabra «Array» y el bloque de White desaparecía entero. Se arregló comparando la ficha de la que
cuelga la fila, subiendo por las relaciones de la vista. Queda apuntado por si el agrupado vuelve a pedirse:
**en Views se compara el dato, no el dibujo.**

**Y una cosa que parecía un fallo y era el dueño trabajando.** Se dijo aquí que la columna de la foto saldría
vacía porque ningún color tenía imagen. A las 13:24, mientras esto se escribía, **aparecieron dos ficheros
nuevos**: el dueño subió las fotos de Black y de White en cuanto tuvo una columna donde verlas. Lo cazó el
guardián, que contaba 71 ficheros y encontró 73, o sea que hizo exactamente su trabajo. El recuento sube a 73
con la explicación puesta.

Del guardián queda **un solo centinela** en vez de los tres del rediseño: que la casilla mide 5.25rem —leído
del fichero, porque lo que se pidió era el ancho y no que hubiera una regla— y que la hoja está enganchada a
la pantalla, que es lo que la carga. **186 de 186.** Prueba de humo, 61 páginas. La prueba del catálogo vuelve
a comprobar lo contrario de lo que comprobaba hace una hora: que **cada fila dice de qué producto y de qué
talla es**, entera, sin agrupar.

### 2026-08-19 (cierre) — La ficha de la marca deja de ser un cartel: es la lista de sus tallas, con el precio editable

La página de una marca enseñaba **una fila por producto**, con su foto y su nombre, y para poner un precio
había que entrar en el producto, bajar al color y abrir la talla. Ahora enseña **una fila por talla** —el
listado entero, al estilo de las líneas de un pedido— con **Foto, Product name, Material, Variation, Size,
Sales price y Material cost**. El precio de venta se escribe en la propia tabla y se guarda con un botón.

**Es una fila por talla porque el precio y el coste viven en la talla, no en el producto.** Un producto no
tiene un precio: tiene tantos como tallas, y un coste distinto por cada una, porque cada talla lleva su
propio BoM. Agrupar por producto obligaba a elegir cuál de los seis números enseñar, y la respuesta correcta
era enseñarlos todos.

**La columna del coste no repite la cuenta: llama a la misma clase que el pie del BoM.** Es la razón de haber
sacado `MaterialCost` a una clase hace un rato. Lo que sí es nuevo es el cuidado con las consultas: una
columna que cargue el BoM fila por fila son seis consultas en una marca pequeña y unos cuantos cientos en la
grande, así que el campo carga **todos los BoM y todos los materiales de la página de golpe** antes de
dibujar nada.

**El precio se guarda con un botón, y no al salir de la casilla.** El módulo que mete formularios dentro de
una vista sabe hacer las dos cosas; guardando al salir de la casilla no había manera fiable de saber **a qué
talla pertenece la fila** que se acaba de tocar, y equivocarse ahí es escribir un precio en la talla de al
lado. Con el botón es un formulario normal, y es además lo que ya hacen los borradores de pedido de esta
casa: la pantalla se comporta igual que las que el dueño ya usa.

**Y de paso se ha arreglado algo que llevaba meses en los borradores de pedido: el rótulo repetido en cada
fila.** Cada casilla editable dibujaba «Sales price» a su izquierda, en las 6 filas, al lado de una columna
que ya se llama así. El módulo tiene una opción para callarlo, pero **solo la aplica a algunos tipos de
casilla** y no a las de número. Se resuelve por el camino de Drupal —el rótulo sigue en el html, escondido,
que es lo que necesita quien navega con lector de pantalla— y como el arreglo mira la configuración de la
vista, **las dos pantallas de borrador de pedido se han quedado limpias sin tocarlas**.

**Una talla a medio crear ya no desaparece.** La primera versión exigía que la talla tuviera su término de
talla, y eso hacía que una talla sin terminar **se cayera del listado sin decir nada**: justo la fila que hay
que arreglar era la única que no se veía. Ahora sale, con la casilla de talla vacía. Hoy no hay ninguna así
—se comprobó, 7 de 7 con término— pero la prueba crea una a propósito para que siga siendo verdad. Es el
mismo criterio que el pie del BoM con los materiales sin precio: **lo que falta se enseña, no se esconde**.

La columna de la foto sale vacía en todas las marcas y **no es un fallo de la pantalla: no hay ni una foto**.
Las imágenes viven en el color, y los tres colores del ERP están sin imagen. La columna se llenará sola en
cuanto se suban.

Comprobado sobre las fichas de marca de verdad, no solo pidiendo la vista: las seis tallas de Rise Fight Gear
con su coste (`฿ 94,05`, `฿ 74,62`, `฿ 74,39`…) y un precio escrito en la tabla que aparece guardado en la
talla. La prueba nueva mira las siete columnas, el orden de las tallas por el peso de su término, que cada
fila traiga su precio, que el coste de dos tallas distintas no sea el mismo y que un `POST` a la página de la
marca guarde. Nueve comprobaciones nuevas en el guardián (la 20): **185 de 185**. Y la prueba de humo, 61
páginas, ninguna rota.

**Con esto se cierra lo que quedaba de la conversación del coste**: el BoM dice lo que cuesta cada talla, y la
marca enseña todas las suyas con lo que cuestan y lo que se cobran, en la misma línea.

### 2026-08-19 (noche) — El BoM de una talla dice por fin lo que cuesta, línea por línea y en total

La tabla de materiales de cada talla —la que sale en la ficha del producto, una por talla— tiene ahora una
columna **Cost** y se cierra por abajo con **Material cost**, el total. Antes enseñaba lo que una talla
consume y no lo que eso vale, así que **el número por el que se pone precio a un producto no estaba en
ninguna pantalla**: había que sacarlo a mano, material por material, de la ficha de cada uno.

Las columnas quedan en cuatro: **Material, Requires, UoU y Cost**. La unidad de compra se ha ido de ahí, y
no por sitio: un BoM dice lo que una talla *consume*, y eso está escrito en unidades de consumo. La unidad
de compra es cosa de la ficha del material y aquí solo separaba la cantidad de su unidad.

**Estaba medio hecho y no se veía, que es la razón de que nadie lo echara en falta.** El módulo que
multiplica dos columnas ya estaba instalado y ya se usaba en las dos vistas de cálculo de material de los
pedidos; lo que no había era una columna así en el BoM. Y al ponerla apareció el porqué de una rareza vieja:
la columna **UoU estaba escondida en la configuración viva y no en la exportada**. O sea que alguien la
ocultó desde la pantalla de Views y no exportó nunca, y la diferencia llevaba ahí lo bastante para que
`drush config:status` la diera por normal. Ahora la receta escribe **quién se ve y quién no**, entero, así
que no depende de lo que alguien dejara a medias.

**El total del pie no es la suma de la columna, y esa es la decisión que importa.** La columna redondea cada
línea al céntimo para poder dibujarla, y hay materiales que valen **seis diezmilésimas de baht** por
centímetro: cada línea de hilo redondea a `฿ 0,00`, y diez líneas de cero siguen siendo cero. Un total
construido sumando lo que se ve diría que un guante se cose gratis. Así que el pie lo suma aparte, en crudo,
y **redondea una sola vez al final** — el mismo error que se cazó el 17 de agosto en las fórmulas de los
pedidos, donde una tela a 0,001 entraba en la cuenta como 0,00 y el ERP decía que era gratis. La prueba lo
comprueba a propósito con diez líneas que a la vista valen cero y un pie que dice `฿ 0,04`.

**Y un material sin coste de consumo no se cuenta como cero callando: el pie lo dice y dice cuál.** Es la
otra cara de lo mismo. Un total al que le falta un material lee como un producto más barato de lo que es, y
nadie va a buscar un número que parece bien. Hoy no hay ninguno —el guardián lo cuenta como dato, no como
fallo, porque no saber todavía lo que cuesta algo es legítimo— pero el importador va a meter 857 materiales
y algunos entrarán sin precio.

La cuenta vive en **una clase, `MaterialCost`**, y no en la pantalla, porque el catálogo de la marca va a
preguntar exactamente lo mismo y dos sitios que multiplican por su cuenta acaban diciendo números
distintos. El pie es un área de Views que la llama.

**Dos tropiezos que costaron media hora cada uno, los dos por callarse.** Un área de Views se declara en la
configuración con la clave `field` puesta al nombre del plugin, no a la palabra `area`: con `area` la
pantalla nombra el pie, Views no encuentra el manejador, **no dibuja nada y no se queja**. Y aunque el
plugin exista, hace falta colgarlo de la tabla global en un `hook_views_data_alter()`, que es un fichero
`tec_inventory.views.inc` que este módulo no tenía: sin él, otra vez, nada dibujado y ni un aviso.

Comprobado sobre la ficha de un producto de verdad, no solo pidiendo la vista: seis tallas, seis tablas de
cuatro columnas y seis pies —`฿ 94,05`, `฿ 74,62`, `฿ 74,39`—. Nueve comprobaciones nuevas en el guardián
(la 19), que mira la fórmula, los dos campos escondidos que la alimentan, el orden de las columnas, que la
pantalla haya dejado de heredar el pie de la maestra y que el área siga ofreciéndose. **176 de 176.** Y la
prueba de humo, 61 páginas, ninguna rota.

~~**Queda la otra mitad de lo que se pidió**: la página de la marca tiene que dejar de ser un catálogo por
producto y pasar a ser un listado por talla, al estilo de las líneas de un pedido, con el **precio de venta
editable ahí mismo** y esta misma columna de coste al lado. La cuenta ya está hecha y compartida; falta la
pantalla.~~ **Hecha el mismo día**, arriba.

### 2026-08-19 (tarde) — El producto suelta al cliente: el campo se ha ido, y la ficha del cliente enseña marcas

Se cerró el corte entero de una sentada. El producto ya no apunta a ningún cliente: **el campo
`field_tec_customer` de `tec_product` está borrado**, y las cinco pantallas que lo leían suben ahora por la
marca. Con el respaldo delante —`antes-de-quitar-el-cliente-del-producto`, las cinco piezas— porque esto
borra un campo con datos dentro y no hay vuelta atrás con `drush`.

**La pestaña Products de la ficha del cliente cambió más de lo que decía el plan, y para bien.** El plan
era cambiarle el argumento; el dueño pidió además verla **agrupada por marca**, y eso se hizo: cada marca
es un bloque con su nombre enlazado a su ficha y **su propio botón «+ Product»** que ya llega con la marca
rellenada y con el camino de vuelta a la ficha del cliente. El botón viejo, el que salía cuando la lista
estaba vacía, llevaba **dos interrogaciones en la misma dirección** y el valor del cliente acababa siendo
`53?destination=/tec_crm/53`: ni rellenaba ni volvía. Ya no existe.

**Y ahí apareció el único sitio donde Views no llegaba.** Los bloques tenían que salir en el orden de
marcas que el dueño arrastra en la ficha del cliente, o sea por la *posición* de cada marca en esa lista.
Ordenar por eso en la consulta significa unir otra vez la tabla de la lista, y esa unión **duplica las
filas**: cada producto salía tantas veces como marcas tuviera el cliente. Así que el orden no lo pone la
consulta: lo pone el módulo **al terminar, cuando la vista se pinta** (`tec_crm_ux_views_pre_render`), que
es reordenar una lista corta que ya está en memoria. Ordenación estable, de modo que **dentro de cada marca
sigue mandando el arrastre** de la pantalla de ordenar. La prueba lo mira por los dos lados y a propósito
con el arrastre al revés del orden de marcas, para que las dos cosas no puedan salir bien por casualidad.

**La pantalla de ordenar productos sigue recibiendo al cliente, y eso era la decisión delicada.** El
módulo `draggableviews` guarda los pesos con los argumentos de la vista pegados a la clave, así que si esa
pantalla pasara a recibir la marca, **el orden arrastrado de ocho clientes se quedaría huérfano sin que
nada avisara**. Sigue recibiendo al cliente: cambia de dónde salen las filas y nada más. La prueba escribe
pesos a mano, comprueba que salen tal cual, y al recoger comprueba que **no se ha llevado las filas de los
demás clientes**.

**Lo mecánico, que era quitar sin pensar mucho, tuvo dos sitios con cuidado.** El paso de la ECA que
copiaba el cliente al duplicar un producto no se podía borrar a secas: los pasos van encadenados y quitar
el del medio corta lo que venía detrás, así que se cosió —quien apuntaba al paso pasa a apuntar a donde
apuntaba el paso— conservando la condición «solo si el producto tiene colores» que colgaba de ese enlace.
Y se editó también el **dibujo** del proceso, no solo el ejecutable, que el guardián compara los dos y
canta si se separan. Lo demás fue el widget del formulario, **el campo dejó de ser obligatorio** —así se
podía crear un producto sin cliente antes de borrar nada, y si el borrado se hubiera torcido no se habría
roto el ERP—, el campo fuera de la maqueta de la ficha, y las columnas y filtros de las listas.

**El corte se midió antes de darlo, y de medirlo salió una lección del método que conviene no olvidar.**
`scripts/quien-lee-el-cliente-del-producto.php` recorrió quién leía el campo y, por cada lector, subió la
cadena hasta encontrar una puerta de verdad: **once lectores vivos y siete muertos**, y de los vivos solo
tres eran recableado —los otros ocho eran quitar columnas y filtros—. La lección costó dos vueltas: **mirar
solo la configuración da un mapa equivocado**, porque las maquetas de las páginas del ERP viven en la ficha
de cada página y ahí hay bloques de vista metidos a mano. `block_1` salía muerto y está vivo: lo embebe la
maqueta de la página «Products». El guion mira ya las dos fuentes.

Al pasar el peine quedaban tres nombres muertos en la configuración, que no rompían nada hoy pero son una
trampa para el siguiente que abra esa pantalla: el **buscador de una sola caja** del bloque de `/p` seguía
teniendo el cliente en su lista de campos por los que buscar, los **ajustes del filtro** que ya no existe
se habían quedado huérfanos, y los grupos de los formularios del producto y de la marca lo seguían
listando como hijo. Barridos en `scripts/barrer-los-restos-del-cliente.php`, que además dice en voz alta
lo que no sabe arreglar.

**Las dos cosas menores que estaban aparcadas se hicieron, y la segunda tapa un agujero que no se había
visto.** La lista de marcas **solo sale si el contacto es cliente**, con el mismo truco que esconde los
campos de compra cuando no es proveedor; esconder es solo para los ojos, así que destildar «Customer» no
borra lo que hubiera guardado. Y las marcas **se ven ya en la ficha**, al lado del tipo de contacto y cada
una enlazada. Esto parecía duplicar la pestaña agrupada y no lo hace: **una marca recién asignada y
todavía sin productos no aparecía en ningún sitio**, porque la pestaña agrupa por marca y una marca sin
filas no pinta bloque. El cliente parecía no comprar esa marca, y no había manera de crearle el primer
producto desde su ficha. Ahora se ve, y desde ahí se llega a la marca, que sí tiene su botón.

**Guardián en 167 comprobaciones, todas en verde, y prueba de humo en 61 pantallas.** La comprobación 17
cambió de pregunta: ya no compara dos sitios —solo queda uno— sino que **exige que ningún producto se
quede sin marca**, que ahora es quedarse sin cliente. Se comprobó antes de borrar que no había ninguno
suelto.

**Tres pruebas de la casa estaban fallando por hechos del mundo y no por el código**, y esto ya había
pasado antes con la misma forma, así que se arreglaron igual: preguntando por la regla y no por el dato
del día. Los enlaces a los catálogos iban a buscar los productos **887, 888 y 889**, que dejaron de existir
el día que se rehizo el juego de pruebas; ahora busca una ficha de cada clase al vuelo, y de paso resultó
que en la parte de los formularios embebidos **una ficha inexistente no reventaba**: pasaba por buena una
pantalla de añadir haciéndose pasar por una de editar. La lista de la compra exigía un número con el
formato `AA-NNN`, de antes de que el código del proveedor entrara en el nombre. Y la prueba de numeración
esperaba `26-001` para una venta sin cliente cuando **existe un pedido de verdad, el 1003, que ya se llama
así**. La cuarta era la pantalla de marcas: para probar la lista vacía despublicaba *la única marca que
había*, y desde que hay dos, la lista nunca quedaba vacía y la prueba se quejaba de una pantalla que estaba
bien.

El aviso que sale al final del guardián, `The reference view /tec_crm_references/ cannot be found`, se
volvió a mirar y **no es de este trabajo**: la vista existe, está encendida y sus cuatro pantallas están;
lo que falla es la comprobación de permisos cuando el formulario se monta desde la consola. En el
navegador no sale.

Las recetas: `scripts/las-pantallas-del-cliente-suben-por-la-marca.php`,
`scripts/la-pestana-agrupa-por-marca.php`, `scripts/el-producto-suelta-al-cliente.php`,
`scripts/adios-al-cliente-del-producto.php`, `scripts/barrer-los-restos-del-cliente.php` y
`scripts/la-ficha-del-cliente-ensena-sus-marcas.php`. Las pruebas nuevas:
`scripts/la-pestana-del-cliente-sale-de-la-marca.php` y
`scripts/las-marcas-solo-salen-si-es-cliente.php`.

Queda del modelo el **orden en dos niveles de las proformas**, que es lo que se gana de verdad, y el
catálogo con precios editables. Y **dos cosas del plan se caen por sí solas**: no hay que borrar las filas
de `draggableviews_structure` guardadas por cliente, porque la pantalla sigue recibiendo al cliente y
siguen valiendo; y la regla del guardián «que la marca de un producto esté en la lista de su cliente» ya no
tiene sentido, porque el producto no tiene cliente al que contradecir.

### 2026-08-19 — Una prueba se comió el juego de pruebas de la casa, y el aviso llegó por la prueba de humo

Incidente propio, contado porque la lección vale más que el susto. La prueba nueva del pedido de un clic
monta su propio juego y lo barre al empezar, para no acumular basura si un día se muere a medias. El
rastro que usaba para reconocer lo suyo era la palabra **PRUEBA**… que es exactamente el prefijo que usa
el **juego de pruebas permanente** del ERP, el que fabrica `scripts/fabricar-el-juego-de-pruebas.php`
para que haya algo que calcular y algo que pedir. Así que el barrido de una prueba borró lo que otras
pruebas necesitaban: la marca, el cliente, el producto con su color y su talla, y el pedido de venta con
su línea.

**Lo cazó la prueba de humo, y por una diferencia de dos.** Pasó de probar 59 pantallas a 57, y las dos
que faltaban eran las del pedido de venta. Ese recuento parecía un detalle sin importancia cuando se
puso; ha resultado ser el detector. El ERP no tiene ni un pedido de venta de verdad, así que **las
pantallas de venta solo se pueden mirar si existe el juego de pruebas**: sin él, la prueba de humo las
salta y dice que todo va bien.

Reparado con las dos escobas de la casa —borrar y volver a fabricar— y sale mejor que antes: **61
pantallas probadas y ninguna sin mirar**, porque el pedido nuevo nace con un estado válido y ahora se
pueden pedir también las dos pantallas de borrador que antes se saltaban. De paso el juego de pruebas
aprendió el modelo nuevo: su cliente ya dice qué marca compra, que si no nacía contradiciéndose y el
guardián lo cantaba.

**El arreglo de fondo**: el rastro de la prueba pasa a ser `PRUEBA excel lover`, largo a propósito, y el
barrido busca los pedidos **por su cliente y no por su nombre** —al guardarlos la ECA de numeración les
cambia el título, así que por nombre no aparecería ninguno—. Sigue empezando por PRUEBA para que la
escoba de la casa recoja también lo de esta prueba. Y se comprobó como se tenía que comprobar: humo antes,
prueba, humo después, mismas 61 pantallas y las mismas direcciones.

Se perdieron por el camino dieciséis líneas de pedido de compra del material de prueba, de una tanda
anterior. Eran de mentira y por la regla de la casa —todo lo que se llama PRUEBA es desechable— tocaba
que se fueran, pero no se fueron a propósito.

### 2026-08-19 — El pedido de un clic sale ya de la marca, y de paso lo recibe quien no lo recibía

Empieza el corte del campo Customer del producto, y empieza por la pieza que más miedo daba: el botón
**«+ Order»** de la ficha del cliente, el que hace de un clic un pedido con una línea por cada talla de
cada producto suyo. Si eso se rompe, el ERP se queda sin poder hacer pedidos de venta, y se rompe
callado.

Por dentro son dos piezas que se llaman por su nombre y no se enteran si la otra cambia: una ECA que la
bandera dispara, y una vista que la ECA consulta pasándole el número del cliente. La vista contestaba
*«las tallas de los productos cuyo cliente es este»*, saltando por el campo que se va. Ahora contesta
*«las tallas de los productos de las marcas que compra este cliente»*. El eslabón nuevo lo ofrece Views
solo, en cuanto existe la lista de marcas del cliente del bloque anterior; comprobar que existía fue lo
primero que se hizo, antes de prometer nada. **La ECA no se ha tocado**: sigue preguntando a la misma
vista con el mismo número. Cambia por dónde busca la vista, no quién pregunta.

**La prueba se escribió antes del cambio y se pasó antes del cambio**, para tener la línea base. Salió
lo que tenía que salir: el cliente de siempre recibía sus dos tallas y el segundo cliente que compra la
misma marca **no recibía nada**. Eso no era un defecto del corte, era el defecto de antes — el producto
solo podía apuntar a un cliente, así que el compañero de marca se quedaba sin su pedido de un clic y
nadie se enteraba. Después del cambio las dos mitades salen en verde. O sea que esto no es solo quitar
un campo: **hay una función que antes no funcionaba y ahora sí**.

Y la prueba no se queda en la consulta, que sería quedarse a medias: **levanta la bandera de verdad**,
la misma que se pulsa en la ficha, con los dos clientes, y mira que el pedido sale con una línea por
talla, con las tallas que son, y que la bandera se baja sola para poder pedir otro.

**El camino nuevo trajo un peligro que el viejo no tenía, y se midió en vez de suponerlo.** La vista
llega ahora al cliente por su lista de marcas, así que una marca apuntada **dos veces** en la lista se
une dos veces y el pedido sale con **cada línea duplicada**: cuatro filas donde había dos. Se arregla
donde nace —la lista es un conjunto, y al guardar se queda una fila por marca— y no con un `DISTINCT` en
la consulta, porque la consulta no es la que está mal. El guardián cuenta duplicados de todas formas,
por el día que algo escriba en el campo sin pasar por ahí.

**Una cosa que no se ha tocado a propósito**, anotada para que no parezca un descuido: la ECA pide el
pedido *sin publicar* y el pedido sale *publicado*. Pasa igual por el camino viejo y por el nuevo, así
que no lo trae este cambio; y además «borrador» no es un estado guardado en el pedido — `/o/draft/N` es
solo una pantalla que lista sus líneas. Queda para cuando se mire el ciclo del pedido de venta entero.

**Y un aviso de nombres**, que aquí se tropieza fácil: `field_tec_customer` existe en dos sitios
distintos. El del **pedido** se queda, es quien dice de quién es el pedido. El que se retira es el del
**producto**.

Guardián en **166 comprobaciones**, y las nuevas vigilan el cable entre las dos piezas, que es donde
esto se rompería en silencio: que la vista busque al cliente por su marca, que el eslabón del que cuelga
siga existiendo, que no haya vuelto a aparecer el salto al cliente del producto, y que siga habiendo una
ECA preguntándole **por la pantalla que es**. Si alguien renombra esa pantalla, la bandera no da error:
hace un pedido vacío, o no hace ninguno. Prueba de humo en 57 pantallas, 0 con problemas —y ahí se ve
que dos pantallas de pedidos de venta no se pueden probar porque el ERP no tiene ni un pedido de venta;
la prueba de hoy es lo único que ha pasado por ese camino.

La receta está en `scripts/el-pedido-de-un-clic-sale-de-la-marca.php` y la prueba en
`scripts/el-excel-lover-mira-las-marcas.php`, que monta su propio juego —una marca, dos clientes, un
producto, un color y dos tallas—, lo llama todo «PRUEBA algo» y lo primero que hace es barrer lo que
lleve ese nombre, para que una prueba que murió a medias no deje clientes de mentira por ahí.

Quedan del corte las otras dos pantallas que leen el cliente del producto —la pestaña Products de la
ficha del cliente y la de ordenar productos— y luego lo mecánico: quitar columnas, filtros, el campo del
formulario y un paso de la ECA que duplica productos.

### 2026-08-19 (madrugada) — Quién compra cada marca se dice ya en un solo sitio, y es una lista

Segundo bloque del modelo de marcas y clientes. Hasta hoy la misma pregunta estaba contestada en **dos**
sitios, y los dos la contestaban mal por el mismo motivo: la marca tenía **una** casilla de cliente y el
producto tenía **una** casilla de cliente. Una casilla, no una lista. O sea que *dos empresas comprando la
misma marca no se podía escribir de ninguna manera* — la única salida era duplicar la marca, con su
logotipo y su catálogo repetidos. Y como nada obligaba a que el cliente del producto coincidiera con el
de su marca, los dos podían contradecirse sin que nada avisara.

Ahora lo dice el cliente, en su ficha, en una lista **sin límite y arrastrable**. Se eligió ese sitio y no
otro por una razón concreta del negocio: hay que poder apuntar las marcas de un cliente nuevo **antes de
que exista ningún producto**, así que la relación no puede deducirse de los productos.

**El orden no es un adorno.** La lista se arrastra porque de ese orden van a colgar las líneas de las
proformas, y el orden lo pone la casa, no el alfabeto. Por eso el guardián vigila que el widget siga
siendo el de varios valores de siempre: es el único que trae la tabla de arrastrar, y cambiarlo por un
desplegable más bonito se llevaría el orden por delante sin dar ninguna señal. Y vigila que quepan
todas: dejarlo en una sola devolvería el problema original sin que nada cambiara de aspecto.

**La mudanza fue de un solo par** —el cliente 53 con su marca Rise Fight Gear— porque el ERP tiene dos
marcas y un producto. La receta la hizo de todos modos leyendo los dos sitios viejos, y **solo borra el
campo Customer de la marca si antes ha comprobado que todos los pares llegaron a su sitio nuevo**. Si
algo no cuadra se para y no borra nada. El almacén del campo se queda, porque lo comparte el vocabulario
de patrones, que no se toca.

**Lo que la prueba comprueba no es que el campo exista** —eso lo ve cualquiera— sino las dos cosas por
las que se cambió: que la misma marca esté en dos clientes a la vez, y que guardar Zoe antes que Aitana
aguante el guardado en ese orden y no se ordene solo. Y una tercera de las que se escapan: que un color
no se pueda colar como marca, porque un desplegable mal atado no se nota hasta que sale impreso.

Guardián en **161 comprobaciones**, con la de fondo que mira los datos: ningún producto apunta a un
cliente que no compra su marca. Mientras el producto siga teniendo su propio cliente los dos tienen que
estar de acuerdo, porque un producto en desacuerdo **cambiaría de dueño el día del corte** y eso se
descubriría después. La prueba entera está en `scripts/una-marca-la-compran-dos-clientes.php`, y
`scripts/quien-compra-cada-marca.php` enseña de un vistazo lo que dice cada sitio.

Queda fuera a propósito: que la lista se esconda cuando el contacto no es cliente —la maquinaria existe,
es la misma del bloque de proveedor— y que las marcas se vean en la ficha del cliente, no solo en su
formulario.

### 2026-08-19 (madrugada) — Cuatro asperezas alrededor de la marca, y una marca vacía que no tenía puerta

Primer bloque del plan de marcas y clientes: lo barato, lo suelto y lo reversible, hecho antes de tocar
nada que pueda romperse. Cuatro cosas que el dueño anotó mirando el formulario de crear una marca y la
ficha de un término.

**El formulario preguntaba tres cosas que nadie contesta.** Relations con su Parent terms, Weight, y
Revision information con su Revision log message vienen gratis con cualquier formulario de taxonomía, y
en este ERP ninguna de las tres hace nada: no hay un solo vocabulario con jerarquía, `new_revision` está
apagado en todos —así que el mensaje de revisión se escribe sobre una revisión que no ocurre— y la
pantalla de marcas **no ordena por nada**, `sorts: {}`, así que el peso no coloca a nadie. El sitio donde
sí se ordenan términos es la pantalla de overview del vocabulario, que escribe ese mismo peso
arrastrando y no necesita la casilla.

**Y ahí estaba la trampa, que es lo único de este bloque que merecía una prueba de verdad:** Weight es
una casilla **obligatoria**. Una casilla obligatoria que nadie puede ver debería bloquear el formulario
para siempre. No lo hace, porque Drupal toma el valor de un elemento inalcanzable de su valor por
omisión, y ese valor es el peso que el término ya tiene. Pero eso no se puede afirmar leyendo el código:
la prueba **envía el formulario de verdad**, con sus fichas de seguridad copiadas del HTML, y comprueba
que la marca se guarda y que sale con peso 0. Se esconden solo en marcas, a propósito: las tres son
igual de inútiles en colores, tallas y materiales, pero esconder algo en todas partes es una decisión
distinta de esconderlo donde se pidió.

**El icono naranja llevaba a un canal vacío.** Lo pone la vista `taxonomy_term` de Drupal, que tiene una
pantalla de tipo canal enganchada a la página, y ese canal lista los **nodos** etiquetados con el
término. Aquí el contenido son entidades propias, así que estaba vacío en marcas, en colores, en tallas
y en materiales. Se apaga la pantalla en vez de borrarla: apagada, Drupal ni la engancha —se va el
icono— ni le da ruta, así que la dirección contesta 404, y si algún día hiciera falta se vuelve con una
casilla.

**La marca ya viene puesta al crear un producto desde su catálogo,** que es lo que pedía el dueño. Lo
hace el módulo `epp`, el mismo que ya rellenaba el cliente. Y aquí hay un detalle que no es cosmético:
**la clave de la dirección tiene que ser distinta.** El cliente se rellena con lo que venga en
`target_id`, así que si la marca usara esa misma clave, crear un producto desde el catálogo de una marca
intentaría escribir una marca en la casilla del cliente. El módulo lo rechazaría al validar, pero
dejaría un aviso en el registro por cada producto. La marca usa `brand_id`, y el guardián vigila que las
dos sigan siendo distintas.

**El enlace «Manage Product Material» que se pidió no se puede hacer, y conviene saber por qué.**
`field_tec_product_material` **no es una taxonomía**: es una lista de valores fijos escrita en la
configuración —semi, leather...—, así que no hay ninguna pantalla que administrar. O se convierte en
vocabulario de verdad, como los tipos de producto, o ese enlace no existe. Lo que sí se ha puesto con el
patrón que ya había es un **«Manage brands»** debajo del campo de marca, porque la marca sí es taxonomía.

**Y por el camino apareció un hueco que nadie había pedido arreglar.** Una marca recién creada no tenía
**nada** en su ficha: ni el aviso de que no tiene productos ni el botón de crear el primero. El módulo
que embebe la vista se calla cuando la vista sale vacía, y eso deja a una marca nueva sin puerta de
entrada, que es justo el caso del prospecto de ventas que el dueño había mencionado —una marca puede
existir antes que sus productos—. Se le ha dicho que la dibuje de todos modos. El guardián lo vigila,
porque es una casilla que alguien puede desmarcar sin entender qué se lleva por delante.

El guardián sube a **155 comprobaciones** con una sección nueva, y la prueba entera está en
`scripts/el-formulario-de-la-marca-va-al-grano.php`.

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
se ha quedado puesta además de la nueva con la marca, porque alguna columna del pattern cuelga de ella y
quitarla habría vaciado columnas. La segunda, el pattern lleva tres enlaces escritos a mano con
`raw_arguments.id` dando por hecho que el argumento es un cliente. La tercera es la que podía hacer
daño: el botón *+ Product* del pattern pasa `?target_id=`, y eso **no es decoración**. El campo de cliente
del producto tiene el módulo `epp` puesto con `[current-page:query:target_id]`, o sea que rellena el
cliente con lo que venga en ese parámetro. Copiarlo tal cual habría escrito el número de una marca en
la casilla del cliente. El botón de la pantalla nueva va sin él.

**El orden del catálogo es por nombre, y es una decisión provisional.** El pattern ordena por el peso que
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
