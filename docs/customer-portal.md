# Portal de clientes — qué tiene que hacer y qué falta para poder construirlo

> Encargo para construir la parte del ERP donde el cliente entra con su cuenta y hace sus
> propios pedidos.
> Escrito en español porque el lector principal es el dueño. **Los textos del portal van en inglés.**
>
> Preparado el 2026-08-13. **Construido y publicado el 29 de agosto de 2026** en
> `https://erp.anvfightgear.com` (`/my`). Aún no hay cuentas Customer. Alta:
> [`sop-give-a-customer-a-portal-login.md`](sop-give-a-customer-a-portal-login.md).
> Lo que sigue abajo es el encargo original; varias “decisiones pendientes” de la
> sección 11 ya están cerradas en el código (enlace usuario→persona→empresa,
> estado `pending_deposit`, rol Customer vaciado, rutas `/my`). Falta la proforma
> por correo.

---

## Para qué sirve este documento

Los requisitos de esta parte del sistema vivían solo en una conversación de chat, y eso es
frágil. Este documento los fija. Quien lo lea después —otro agente, un desarrollador, o el
propio dueño dentro de seis meses— tiene que poder construir el portal sin volver a preguntar
nada, salvo las decisiones que en la sección 11 están marcadas como pendientes.

**Estado a 29 de agosto de 2026: el portal está construido y en producción** (`/my`),
sin cuentas Customer todavía. La sección 3 describe el flujo de fábrica (*pedido de un
clic*) que **no** es el portal; no se reutilizó esa piel.

---

## 1. Por qué se quiere

Hoy el pedido lo monta a mano un empleado de la fábrica, copiando lo que el cliente manda por
WhatsApp o por correo. Eso cuesta dos cosas:

**Malentendidos.** El clásico "te dije 25 guantes, no 205". Cuando la cifra la teclea el
cliente, la discusión se acaba: lo que hay en el sistema es lo que él escribió, con su fecha y
su cuenta detrás.

**Tiempo.** Muchas horas de conversación para recoger algo que el cliente puede introducir él
mismo en dos minutos.

---

## 2. El recorrido del cliente

1. Llega a la web y ve la pantalla de login.
2. Entra con su cuenta de cliente.
3. **Nada más entrar ve sus pedidos.** Solo los suyos, y nada más en la pantalla.
4. Hay un botón para crear un pedido nuevo.
5. Al pulsarlo aparecen **únicamente sus productos**, con las columnas de la rejilla de líneas
   de pedido.
6. Rellena las cantidades.
7. Guarda el pedido.
8. El sistema le devuelve al punto 3, al listado de sus pedidos.

---

## 3. Qué existe hoy en el ERP, y por qué no es esto

El desarrollador anterior construyó lo que él llamó **el flujo "Excel lover"**, hoy **el pedido de
un clic** —el nombre se cambió el 19 de agosto de 2026, en configuración y en la dirección del
botón—. Se parece por fuera a un portal, pero es otra cosa: es una **pantalla para que la use el
personal de fábrica**, no el cliente.

Funciona así. En la ficha de una empresa del CRM hay una marca, el *flag*
`tec_order_draft_sales`. Al pulsarla, una automatización de ECA (`process_sclj26d`) crea
un pedido con una línea por cada talla de cada producto de ese cliente y redirige a
`/o/draft/{id}`. Ahí la vista `tec_order_sales_order_line_items` pinta la rejilla editable de
cantidades. Se puede ver funcionando en una instalación con datos, por ejemplo en
`/o/draft/752`.

**Lo que está aprovechable de todo eso:**

- La rejilla de cantidades ya existe y ya respeta el orden manual de los productos.
- **Solo la cantidad es editable.** Usa el plugin `entity_form_field`, mientras que el precio y
  el importe son campos de solo lectura. Es exactamente el comportamiento que se quiere.
- La automatización que genera las líneas a partir del catálogo del cliente ya existe.
- El modelo de datos que relaciona producto, cliente, pedido y línea está entero.
- **La proforma ya tiene página de impresión**, el display `page_4` de esa misma vista, en
  `o/pf/{id}/print`. O sea que el documento está maquetado: lo que falta es generarlo y
  mandarlo por correo solo.

**Lo que no sirve:**

- Está pensado para que lo ejecute un empleado desde el CRM, no para que un cliente entre solo.
- **El botón de checkout no hace nada.** El flag `tec_order_checkout` está desactivado
  (`status: false`) y la automatización asociada (`process_v0gguvo`) es literalmente un mensaje
  que dice que la magia llegará en la próxima versión. La proforma automática **hay que
  construirla entera**.
- No hay ninguna pantalla de "mis pedidos".
- El rol Customer está inservible y además es peligroso. Ver sección 9.
- **Hay dos pantallas escritas para la misma dirección `o/draft/%`**, los displays `page_2` y
  `sales_order_draft_form_unused`, y no enseñan las mismas columnas: una lleva la de "Sales price"
  y la otra no. La segunda está **apagada** (`enabled: false`), así que hoy no compite con nadie y
  la que sirve es `page_2` —cuando esto se escribió parecía un empate y no lo era—. Sigue siendo
  peso muerto: hay que borrarla antes de construir el portal encima.

---

## 4. Qué puede hacer el cliente con un pedido

Muy poco, y es a propósito.

| Situación | Qué puede hacer |
|---|---|
| El pedido está abierto | Cambiar cantidades. Nada más. |
| El cliente está seguro | Pulsar **Confirmar**. Hace falta un botón explícito para esto. |
| Ya confirmado | Nada. Deja de ser editable. |

Al confirmar se dispara la cadena de siempre: **la proforma le llega automáticamente a su
correo**.

**El cliente sí ve precios mientras monta el pedido.** La rejilla lleva las columnas
"Sales price" e "Item total" por línea, y abajo una fila de "Total" que suma el pedido entero.
No es una decisión pendiente: es como ya está construida la pantalla. La consecuencia
operativa importante está en la sección 11.

---

## 5. Qué no ve y qué no puede hacer

Esto es tan importante como lo anterior, y conviene leerlo como una lista cerrada.

- **No ve** material calculations.
- **No ve** el summary del pedido.
- **No ve** production documents.
- **No ve** ninguna otra parte del ERP: ni la cola de producción, ni el stock, ni el CRM, ni
  los informes.
- **No ve** los estados internos de fábrica. Ver sección 6.
- **No puede** añadir productos al catálogo. Su catálogo es el que la fábrica le haya montado.
- **No puede** crear, editar ni borrar nada que no sean las cantidades de un pedido abierto.

Resumido: el cliente solo ve líneas de pedido, y su único poder es poner cantidades y decidir
cuándo el pedido está confirmado.

---

## 6. Los estados que ve el cliente

El cliente **sí ve sus pedidos pasados**, pero con los estados filtrados. Los diez estados
internos que hay hoy en `field_tec_order_status` son estos:

`draft` (Open), `accounting_verified`, `in_progress_processing`, `ready_for_production`,
`production_started`, `quality_control_inspection`, `completed`, `ready_for_delivery`,
`shipped_delivered`, `cancelled`.

Enseñárselos todos sería un error. El dueño señaló un caso concreto: un estado del tipo
"In production" **da una impresión equivocada**, porque el cliente entiende que sus guantes
están en la máquina cuando a lo mejor solo se han comprado los materiales.

**Propuesta de traducción**, pendiente de que el dueño la cierre:

| Estado interno | Lo que ve el cliente |
|---|---|
| `draft` | **Open** — y significa que todavía puede modificarlo |
| *(estado nuevo, ver decisión 2 de la sección 11)* | **Pending deposit** |
| `accounting_verified` | **Confirmed** |
| `in_progress_processing` | Confirmed |
| `ready_for_production` | Confirmed |
| `production_started` | Confirmed |
| `quality_control_inspection` | Confirmed |
| `completed` | Confirmed |
| `ready_for_delivery` | **Ready to ship** |
| `shipped_delivered` | **Shipped** |
| `cancelled` | **Cancelled** |

La idea es que, desde que el pedido se valida hasta que está listo, el cliente vea **una sola
palabra que no cambia**. Nada de seguir el pulso de la fábrica.

---

## 7. El orden de los productos

Hay clientes con **más de cien líneas** en un pedido. Esto no es un problema abierto: la
fábrica ya ordena los productos a mano, con el mismo criterio que usa en las proformas
manuales, y ese orden ya está guardado en el sistema.

El orden tiene cuatro niveles y cada uno lo decide otra cosa: las **marcas**, por la lista de
la ficha del cliente; los **productos**, por el sitio que se les arrastra en la pestaña
*Organize products* de la ficha de la marca; los **colores**, por la lista de la ficha del
producto; y las **tallas**, por el peso del vocabulario. Una proforma nace ya en ese orden y
desde ese momento se congela: cada pantalla la lista por el número de la línea, así que volver
a arrastrar una marca cambia la proforma siguiente y no las ya emitidas.

Hasta el 19 de agosto de 2026 el orden de los productos era **del cliente** —se arrastraba en
`/tec_crm/{id}/reorder` y se guardaba con *draggableviews*—, y esa pantalla ya no existe: eran
ocho ordenes del mismo catálogo y ninguno le servía a la proforma. Está contado en el README
del módulo `tec_production`, sección *The brand decides the order of its products*.

**Requisito para el portal: respetar ese mismo orden interno.** No hay que inventar ninguna
ordenación nueva, ni alfabética, ni por categorías. Y para leerlo no hace falta calcularlo: es
el orden en que ya vienen las líneas del pedido.

---

## 8. Qué pasa por dentro cuando el cliente confirma

Esto es importante para no malinterpretar el significado de "confirmado".

Cuando el cliente confirma, **el pedido entra directamente en la fábrica**. Pero lo que lo
valida de verdad no es su confirmación: es **el pago del depósito**. La fábrica trabaja con un
mínimo del **50% por adelantado**.

El circuito real es:

1. El cliente confirma.
2. La fábrica comprueba si ha entrado el dinero.
3. Cuando ha entrado, un humano pone el pedido en **Accounting Verified**.
4. A partir de ahí empieza todo: compra de materias primas, planificación, producción.

Es decir, entre el paso 1 y el paso 3 hay un limbo. Hoy ese limbo no existe como estado en el
sistema, y es una de las decisiones pendientes.

---

## 9. El agujero del modelo de datos, y dos trampas

### 9.1 Falta un eslabón, y sin él no hay portal

Para que el sistema pueda responder "¿de quién son estos pedidos?" hacen falta cuatro enlaces.
**Tres ya existen:**

| Enlace | Cómo | Estado |
|---|---|---|
| Pedido → empresa | `field_tec_customer` en `tec_sales_order` | Existe |
| Producto → empresa | `field_tec_customer` en `tec_product`, obligatorio | Existe |
| Persona → empresa | `field_tec_works_at` en `tec_contact_person` | Existe |
| **Cuenta de usuario → persona o empresa** | — | **NO EXISTE** |

La entidad usuario de Drupal en este proyecto **solo tiene un campo propio, la foto**. No hay
absolutamente nada que ate una cuenta con un cliente. Mientras eso siga así, el punto 3 del
recorrido ("nada más entrar ve sus pedidos") es técnicamente imposible.

### 9.2 Trampa: los permisos "own" van por autor, no por empresa

Los permisos que dejó el desarrollador anterior son del tipo `view own` y `edit own`. En ECK,
**"own" significa "la entidad la creó ese usuario"**. Eso no vale aquí por dos razones:

- Los pedidos reales los crea la fábrica, así que el cliente nunca sería el autor y no vería
  nada.
- Si en una empresa cliente piden dos personas distintas, cada una vería solo lo suyo y no lo
  de su empresa.

**El filtro correcto es por empresa, no por autor.** Eso implica que los permisos estándar de
ECK no sirven y hay que resolverlo con una vista filtrada por la empresa del usuario más un
control de acceso propio en las páginas de pedido.

### 9.3 Trampa: el rol Customer es peligroso tal y como está

El rol `tec_customer` tiene hoy 19 permisos, y entre ellos están estos:

```
create tec_product entities of bundle tec_product
create tec_product entities of bundle tec_color_variation
create tec_product entities of bundle tec_size_variation
create tec_inventory entities of bundle tec_inventory_item
create tec_inventory entities of bundle tec_inventory_transaction
create tec_inventory entities of bundle tec_bom_item
create tec_crm entities of bundle tec_contact_person
```

Un cliente con ese rol podría **crear productos y meter movimientos de inventario** en el
almacén de la fábrica. Hoy no hay ninguna cuenta con ese rol, así que no hay riesgo real, pero:

> **El rol Customer hay que vaciarlo y reconstruirlo desde cero antes de que exista la primera
> cuenta de cliente.**

Como nota adicional, el rol tampoco tiene `access content`, así que ni siquiera podría ver la
página de inicio. Y la vista de los iconos del escritorio (`tec_icons_launchpad`) no filtra por
rol, o sea que si se le diera acceso vería los iconos de toda la fábrica.

---

## 10. Cómo construirlo

Esta sección tiene dos partes que conviene no mezclar. La primera es **el suelo de seguridad, que
no es opcional** y hace falta igual sea cual sea el camino. La segunda son los tres caminos
posibles, que se diferencian en cuánto hay que construir y en cuánta superficie hay que vigilar
después.

### Lo que no es opcional, elijas el camino que elijas

Esto no hay que inventarlo: está resuelto desde hace años y siempre de la misma manera.

En **Odoo**, un cliente no es un usuario del ERP con menos botones: es otro tipo de usuario,
"portal", que estructuralmente no puede entrar al backend. No es que se le oculte, es que esa
puerta no existe para él. Navega por `/my/orders`, y lo importante es lo que **no** hay en esa
dirección: ningún identificador de cliente. El sistema sabe quién es por la sesión. Por debajo
hay *record rules*, reglas que el ORM añade a todas las consultas de ese usuario, venga la
consulta de donde venga.

**Magento, Shopify y WooCommerce** hacen lo mismo: `/account/orders`, front-end separado del
admin, y el controlador carga los pedidos por el identificador de la sesión, nunca por un
parámetro de la dirección. **SAP** igual, con una aplicación aparte y las autorizaciones
comprobadas en la capa de datos.

Todos comparten una sola idea: **la seguridad vive por debajo de la pantalla, y la identidad del
cliente sale de la sesión, jamás de la URL.** De ahí salen cuatro reglas.

**1. Dos puertas, no una puerta con cortinas.** El cliente no entra nunca a la interfaz del
personal. Otro tema visual, otras rutas, y fuera el permiso de ver el tema de administración.
Importa más de lo que parece, porque la interfaz del ERP tiene muchas puertas laterales: la ruta
`/quicktabs/nojs/{instancia}/{pestaña}`, que solo exige `access content`; los endpoints ajax de
las vistas; los filtros expuestos; los enlaces contextuales; las descargas de
`views_data_export`. Recortar una página no cierra ninguna de ellas.

**2. La identidad sale de la sesión, nunca de la URL.** Una dirección como `/customer/25` lleva
el cliente escrito dentro, y eso se puede cambiar a mano. La forma correcta para el listado es
`/my`, sin identificador: así no hay nada que manipular. Para el detalle sí hace falta
`/my/order/754`, y ahí la comprobación es obligatoria.

**3. El permiso se comprueba en el dato, no en la pantalla.** Ocultar pestañas y columnas es
maquillaje. Si un cliente escribe `/customer/26` o `/tec_order/755` en la barra del navegador,
esas direcciones existen, y si lo único que se ha hecho es quitar pestañas verá la ficha entera
de otro cliente. Hace falta un enganche de acceso, escrito una sola vez: un cliente solo puede
ver la ficha de su empresa y los pedidos cuyo `field_tec_customer` sea su empresa. Eso protege
todas las direcciones a la vez, incluidas las que se nos olviden. Y en Drupal hay que añadir una
segunda capa, un filtro contextual en las vistas cuyo valor por defecto sale de la sesión, porque
un listado puede pintar filas que el control de entidad no filtra por sí solo. Cinturón y
tirantes.

**4. Denegar por defecto.** El rol se construye desde cero añadiendo lo imprescindible, nunca
copiando el de un empleado y quitando cosas. Restar es frágil, porque siempre se olvida algo, y
lo que se olvida no se nota hasta que alguien lo ve.

### Opción A: pantallas nuevas, solo para clientes

Rutas propias, por ejemplo `/my/orders` y `/my/order/{id}`, reutilizando las mismas vistas pero
con displays pensados para el cliente.

- **A favor:** las pantallas del personal se quedan como están y pueden seguir cambiando sin
  riesgo de filtrar nada. Hay un solo sitio donde mirar cuando se audite la seguridad.
- **En contra:** hay que construir tres pantallas desde cero.

### Opción B: recortar las pantallas que ya existen

Es la propuesta del dueño, y aprovecha que el ERP ya tiene montado casi todo:

1. El cliente entra en una versión recortada de `/customer/{id}`, la ficha de su empresa, donde
   ya están las pestañas **Orders**, **Products** y **Details**.
2. Al pulsar **+ Order** va a `/o/draft/{id}`, la rejilla de cantidades que ya existe. Si
   cancela o borra, vuelve a `/customer/{id}`.
3. Al guardar va a una versión muy recortada de `/tec_order/{id}` en la que **solo** ve la
   pestaña de Line items, sin material calculation, sin material summary y sin production
   documents.

**Es viable, y el mecanismo ya viene en el módulo.** La página del pedido no es un montaje
complicado: es una cabecera con cuatro campos y, debajo, un único bloque de QuickTabs con
cuatro pestañas.

| Pestaña del pedido | Qué la pinta |
|---|---|
| Line items | `views_block:tec_order_sales_order_line_items-block_1` |
| Material calculation | `views_block:tec_order_material_calculation_terms-block_1` |
| Material Summary | vista `tec_order_material_calculation_summary`, display `block_1` |
| Production Documents | bloque `tec_order_production_documents` |

QuickTabs comprueba el acceso de cada bloque antes de pintarlo y devuelve vacío si no lo hay;
está en `ViewContent::render()` y en `BlockContent::render()`. Y la opción **"Hide empty tabs"**,
hoy apagada, hace que además desaparezca la pestaña entera: *"Empty and restricted tabs will not
be displayed"*.

O sea que para recortar la página del pedido basta con **restringir el acceso a las tres vistas
que el cliente no debe ver y encender "hide empty tabs"**. No hay que rehacer la página.

> **Condición obligatoria: el ajax de QuickTabs tiene que seguir apagado.** El propio módulo
> avisa de que, con ajax encendido, los bloques quedan accesibles aunque pongas restricciones de
> rol, y de que "hide empty tabs" deja de funcionar. Hoy las dos instancias están en
> `ajax: false` y tienen que quedarse así.

**El punto débil de la B**, y hay que decirlo claro, es que las pantallas del personal y las del
cliente pasan a ser la misma cosa para siempre. Cualquier pestaña o columna que alguien añada
dentro de dos años la verá también el cliente, salvo que en ese momento se acuerde de
restringirla. Es una deuda que no se paga una vez, se paga cada vez que se toca el ERP.

### Opción C: el contenido de la B con la fontanería de la A — **la recomendada**

Es la síntesis de las dos anteriores, y es la que yo construiría.

**Por fuera se comporta como la A.** Rutas nuevas, propias del cliente:

| Ruta | Qué hace |
|---|---|
| `/my` | Sus pedidos. **Sin identificador en la dirección**: la empresa sale de la sesión. |
| `/my/order/new` | Crea el pedido y lleva a la rejilla de cantidades. |
| `/my/order/{id}` | El detalle de un pedido suyo. Aquí la comprobación de acceso es obligatoria. |

El cliente navega con el tema del front, nunca pisa la interfaz del ERP, y su rol se construye
desde cero.

**Por dentro reutiliza todo lo de la B.** No se rehace ninguna pantalla:

- La misma rejilla de cantidades que ya existe en `/o/draft`.
- La misma vista de líneas de pedido, con un display propio que enseñe solo las columnas que
  debe ver.
- La misma automatización que genera las líneas a partir del catálogo del cliente.
- El mismo documento de proforma que ya está maquetado en `o/pf/{id}/print`.

**Por qué esta y no las otras.** Frente a la B, evita que el ERP interno y el portal sean la
misma pantalla, que es una deuda permanente. Frente a la A, no paga el coste de construir las
pantallas, porque ya están hechas.

**Lo que cuesta.** La fontanería de la A: las rutas y unas noventa líneas de código repartidas
así.

| Pieza | Tamaño aproximado |
|---|---|
| Ayudante que resuelve "de qué empresa es este usuario", con caché | ~20 líneas |
| Enganche de acceso por empresa sobre pedidos, productos y fichas de CRM | ~50 líneas |
| Complemento de Views que pone la empresa de la sesión como filtro por defecto | ~40 líneas |

Ese código va en un módulo propio o en el que ya existe, `tec_crm_ux`, donde además **ya está
escrito el patrón a seguir**: la función `tec_crm_ux_entity_field_access()` hace exactamente este
tipo de comprobación para ocultar los campos de proveedor en los contactos que no lo son.

**Un detalle que se le roba a Odoo.** El enlace de la proforma que se manda por correo no debería
ser un `/tec_order/754` pelado, sino un enlace **firmado para ese pedido concreto**. Así el
cliente abre su proforma desde el móvil sin loguearse, y ese enlace no sirve para ver nada más.

### Lo que hay que quitar de esas pantallas

Repasando la ficha de cliente tal y como se ve hoy:

- La columna **"Created by"** enseña los nombres del personal de la fábrica.
- La columna **"Order status"** enseña los estados internos, del tipo "In Processing".
- La columna **"Priority"** es información de planificación vuestra.
- El enlace **"Add contact person"**.
- La fila de pestañas **VIEW / EDIT / DELETE / MANAGE DISPLAY**, que desaparece sola en cuanto
  el rol no tenga permisos de edición.

**Una comprobación con buen resultado:** la pestaña **Products** solo tiene una columna de
dinero, "Sales price". No hay coste ni margen por ningún lado, así que esa pestaña es segura tal
y como está.

Sobre el paso 2, cambiar el destino para que Cancel vuelva a la ficha del cliente es trivial: es
el parámetro `destination` de la propia dirección. Lo que sí hay que decidir es si el botón de
**Delete** debe existir para el cliente y, en tal caso, limitarlo a borrar únicamente pedidos
suyos que sigan abiertos.

### ¿Hay algún módulo que nos ahorre programar esto?

Se buscó, y la respuesta corta es que **no existe un módulo de Drupal que dé este portal montado**.
Merece la pena dejar escrito por qué, para que nadie repita la búsqueda dentro de un año.

El ecosistema resuelve este problema por dos caminos y ninguno encaja aquí. Si tienes una tienda
usas **Drupal Commerce**, que trae el área de cliente hecha, pero estos pedidos no son pedidos de
Commerce, son entidades ECK. Y si lo que necesitas es "los usuarios pertenecen a una organización
y solo ven lo suyo", la respuesta estándar es **Group**, que es un módulo serio y mantenido.

Group se descartó por un motivo concreto: funciona relacionando cada contenido con un grupo, o
sea que habría que crear una relación por cada pedido, por cada producto y por cada ficha de CRM,
y eso **duplica el campo `field_tec_customer` que ya existe** y que ya dice de quién es cada cosa.
Acabarías manteniendo dos estructuras que afirman lo mismo, con el riesgo de que se
desincronicen. Es más trabajo, no menos.

Los módulos populares de permisos fila a fila, `permissions_by_term`, `content_access` y
`access_by_ref`, están construidos **solo para nodos**. Este ERP no usa nodos para nada de esto,
así que no aplican.

**Lo que sí hay, y además ya está instalado y activado**, son tres piezas que ayudan:

| Módulo | Para qué sirve aquí |
|---|---|
| `eca_access` | Implementa `hook_entity_access`, `hook_entity_field_access` y `hook_entity_create_access`, y los expone como reglas de ECA. Permite escribir control de acceso **sin PHP**. |
| `masquerade` | Deja suplantar una cuenta de cliente desde un usuario del personal. Es la única forma seria de comprobar que el portal está bien cerrado. |
| `r4032login` | Manda al login a quien entre sin permiso, en vez de soltar un "acceso denegado". Es el comportamiento que se quiere cuando un cliente pincha un enlace viejo. |

Sobre `eca_access` conviene ser explícito, porque es tentador: **para esta regla concreta es mejor
PHP que ECA.** Tres motivos. Es la pieza que no puede fallar nunca, y en código se revisa en un
diff de git, no en un diagrama que cualquiera puede abrir y mover sin querer. Se ejecuta en cada
entidad de cada página, así que en un listado de cien pedidos se evalúa cien veces, y una función
con caché es mucho más barata que un modelo. Y el patrón ya está escrito en el proyecto.

ECA sí es el sitio adecuado para el resto: el cambio de estado al confirmar, el envío de la
proforma y los avisos.

**Un aviso suelto que salió de esta búsqueda:** `views_data_export` está activado. Cualquier
vista con un display de exportación es una dirección que descarga datos en crudo, y hay que
repasarlas una por una antes de abrir el portal.

---

## 11. Decisiones pendientes

Ninguna de estas es técnica: todas las tiene que cerrar el dueño.

**1. Cómo se conecta la cuenta de usuario con la empresa.** Dos caminos:

- *Usuario → persona de contacto → empresa.* Respeta el CRM que ya existe, permite varias
  personas por empresa y deja rastro de quién pidió qué. Un enlace más largo.
- *Usuario → empresa directamente.* Un solo campo, más simple, pero se pierde quién es quién
  dentro del cliente.

Recomendación: el primero, porque el campo `field_tec_works_at` ya existe y hace la mitad del
trabajo.

**2. Qué estado tiene el pedido entre que el cliente confirma y la fábrica ve el depósito.**
Si se queda en `draft` el cliente sigue pudiendo editarlo. Si salta a `accounting_verified` se
está afirmando que ha pagado cuando aún no. Lo más limpio parece un estado nuevo, tipo
"Pending deposit", que además le recuerda al cliente que le toca pagar.

**3. La lista definitiva de estados que ve el cliente.** La tabla de la sección 6 es una
propuesta, no una decisión.

**4. Si el cliente ve precios. RESUELTA: sí los ve.** Se deja anotada porque la consecuencia no
es menor. La rejilla ya enseña "Sales price", "Item total" y el total del pedido, así que en
cuanto haya portal **el cliente estará leyendo la tarifa que tenga guardada el ERP**. Hoy esos
precios solo los ven los empleados, y si alguno está desfasado no pasa nada porque alguien lo
corrige al hacer la proforma a mano. A partir del portal, un precio viejo es un problema
comercial en cuanto el cliente lo ve. **Antes de abrir el portal hay que repasar y dar por
buena la tarifa de cada cliente**, y decidir quién se responsabiliza de mantenerla.

**5. Quién crea las cuentas de cliente y cuántas hay por empresa.** ¿Una sola compartida, o una
por persona de contacto?

**6. Qué pasa si el cliente se arrepiente después de confirmar.** ¿Llama por teléfono y lo
arregla la fábrica a mano, o hay algún mecanismo en el portal?

**7. Si el pedido que crea el cliente nace como borrador o como pedido de venta.** En el ERP
conviven los bundles `tec_draft_order` y `tec_sales_order`, y el flujo del desarrollador
anterior mezcla los dos. Hay que aclararlo antes de escribir código.

**8. Cuál de los tres caminos de la sección 10.** La B es la que menos trabajo parece, pero deja
el ERP interno y el portal fundidos en la misma pantalla para siempre. La A es la más limpia y la
más cara. **La C es la recomendada**: rutas y seguridad de la A, pantallas reutilizadas de la B.
Sea cual sea la elegida, el suelo de seguridad de la sección 10 hay que ponerlo igual.

**9. Si el cliente puede borrar un pedido**, y si ese permiso se limita a los suyos que aún
estén abiertos.

---

## 12. Orden de construcción propuesto

Por dependencias, no por importancia.

1. **Atar la cuenta de usuario con el cliente.** Sin esto no se puede hacer nada más.
2. **Reconstruir el rol Customer desde cero**, con la lista cerrada de la sección 5 como guía
   de lo que *no* puede hacer.
3. **El control de acceso por empresa, en código**, antes de enseñar ninguna pantalla. Es lo
   que protege las direcciones que se escriben a mano. Ver la sección 10.
4. **La pantalla "mis pedidos"**, filtrada por empresa, con los estados traducidos.
5. **El botón de pedido nuevo**, reutilizando la automatización que ya genera las líneas.
6. **El botón de Confirmar**, con el bloqueo de edición y el cambio de estado.
7. **La proforma automática por correo.** El documento ya está maquetado, en `o/pf/{id}/print`,
   así que lo que falta es generarlo y enviarlo solo.

Los tres primeros son los que más peligro tienen si se hacen mal, así que conviene revisarlos
con calma antes de seguir. Del cuarto en adelante, la forma concreta depende del camino elegido
en la sección 10; con la opción C, que es la recomendada, esos pasos son sobre todo montar rutas
y displays sobre pantallas que ya existen.

---

## 13. Cuándo empezar

Este trabajo **no debe empezar hasta que el modelo de datos esté asentado**: después de la
limpieza de datos de prueba y después de que Lukpla haya revisado el ERP y haya dicho qué
cambios necesita. Construir el portal sobre un modelo que va a moverse sería tirar el trabajo.
