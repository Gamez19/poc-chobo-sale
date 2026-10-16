# Simplified purchases (replace recipes and raw-material stock)
Branch: feat/simplified-purchases. Raw materials become a name-only catalog; purchases record a total and a checklist of materials.
Production no longer depends on raw-material stock; profit = sales − purchases of the period.

## Specs
- S1: "en lugar de agregar recetas a variantes de produtos y manejar los montos y ganancias en base a eso, creo que sería mejor tener un solo modulo que registre las compras o los gastos." → Recipes are no longer configured or used.
- S2: "Por ejemplo: Hoy fui a comprar 5 docenas de bananos: c$ 150, 3 bolsas de chocolates: c$180, eso registrarlo en el módulo"
- S3: "mi producción de lotes no dependa de cuanta materia prima tengo disponible" / "el sistema no me deja agregar ese lote de producción porque a nivel de sistema ya no tengo" → creating or editing a lot never checks or deducts raw-material stock.
- S4: "solamente tener un módulo de materias primas, donde se registen los insumos solamente por nombre."
- S5: "cuando vaya hacer compras solamente registrar el total que gasté y hacer un check list de los insumos que compré."
- S6: Profit decision (user choice): "Ventas − compras del período (Recomendado)" — profit = total sold in the date range − total spent on purchases in that range.
- S7: Existing data decision (user choice): "Conservar historial, dejar de usarlo" — old recipe, stock, movement and lot cost data stays in the database, unused and not shown.

## Tasks
- T1 (S2, S5, S4): purchases tables + models (purchase total, date, notes, checklist of raw materials). route: inline. commit: 4585d7a
- T2 (S4, S5, S2): Raw materials page = name-only catalog + register purchase + purchase history. route: inline. commit: 4585d7a
- T3 (S1, S3, S7): ProductionService without recipe/stock; Products without recipe UI. route: worker. commit: aa31d66
- T4 (S6, S7): Profits = sales − purchases of period; Dashboard drops low-stock card. route: worker. commit: 11f00ce
- T5 (all): Seeder, dead code, README. route: inline. commit: ed4b2fa

## Log
- L1 (user, verbatim): "Necesito crear un cambio en el proyecto.
Actualmente, cada vez que se crea un lote se tienen que ingresar datos al módulo de materia prima, cada receta de variante producto puede cotener varias combinaciones de materias primas lo que en un punto puede dificultar su ingreso.
Necesito que sea más sencillo de manejar, en lugar de agregar recetas a variantes de produtos y manejar los montos y ganancias en base a eso, creo que sería mejor tener un solo modulo que registre las compras o los gastos.
Por ejemplo: Hoy fui a comprar 5 docenas de bananos: c$ 150, 3 bolsas de chocolates: c$180, eso registrarlo en el módulo; sin embargo mi producción de lotes no dependa de cuanta materia prima tengo disponible. Por ejemplo, el chocolate, mani y chispas son complicado de manejar el consumo por chocobanano, por eso a veces a nivel de aplicación ya no tengo chocolate, pero en la vida real si tengo y el sistema  no me deja agregar ese lote de producción porque a nivel de sistema ya no tengo y me toca agregar una entrada y fingir que fui a comprar chocolate.

Por eso propongo que lo más sencillo es solamente tener un módulo de materias primas, donde se registen los insumos solamente por nombre.
Luego cuando vaya hacer compras solamente registrar el total que gasté y hacer un check list de los insumos que compré."
- L2 (user decisions): profit = "Ventas − compras del período (Recomendado)"; existing data = "Conservar historial, dejar de usarlo".
- L3 (derived decision): purchases are not tied to lots, so the Profits lot filter and per-lot cost columns are removed; the projected section keeps only potential revenue of available stock. UI copy stays in Spanish (existing project convention).
- L4 (evidence): slice 1 = 4585d7a (T1+T2, ~970 lines, mostly whole-view rewrite); tests PurchasesTest 7/7, suite 105/105. Next slices T3, T4+T5 committed separately.
- L5 (evidence): T3 aa31d66, T4 11f00ce, T5 ed4b2fa; full suite 103/103 after each. Context backstop moved T3/T4 to parallel workers. Ingreso potencial on Profits is independent of the date range (current stock).
