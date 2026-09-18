<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Acciones';
$string['addnew'] = 'Nuevo informe SQL';
$string['createfeaturesnote'] = '"Publicar y continuar editando" para desbloquear más opciones — gráficos y filtros por usuario y por curso — que necesitan las columnas del informe ya publicado para poder configurarse.';
$string['ai:copied'] = 'Copiado';
$string['ai:copy'] = 'Copiar';
$string['ai:generate'] = 'Generar SQL';
$string['ai:generatedname'] = 'Consulta generada';
$string['ai:generating'] = 'Generando…';
$string['ai:heading'] = 'Generar SQL con IA';
$string['ai:heading_help'] = 'Describa en lenguaje sencillo los datos que desea, y a continuación pulse **Generar SQL**. La IA escribe una consulta SELECT en el editor SQL de abajo.

Por ejemplo: "Mostrar todos los estudiantes matriculados en más de 3 cursos".

También puede hacer referencia al SQL ya presente en el editor — instrucciones como "añade una columna a esto", "muestra también la dirección de correo" o "corrige este error" utilizan su consulta actual como punto de partida en lugar de crear una nueva desde cero.

En particular, comenzar su instrucción con la palabra **también** incorpora su SQL existente y construye sobre él — por ejemplo "también muestra el último acceso del usuario" añade a la consulta actual en lugar de sustituirla.

Revise siempre el SQL generado antes de guardar — la IA puede cometer errores.';
$string['ai:history'] = 'Sus preguntas recientes';
$string['ai:historyempty'] = 'Todavía no hay historial. Genere una consulta y aparecerá aquí.';
$string['ai:historyload'] = 'Cargar SQL';
$string['ai:historywhen'] = 'Cuándo';
$string['ai:latency'] = 'Generado en {$a} s — revise el SQL antes de guardar.';
$string['ai:placeholder'] = 'p. ej. Mostrar todos los estudiantes matriculados en más de 3 cursos';
$string['ai:prompt'] = 'Instrucción enviada al LLM';
$string['ai:question'] = 'Describa los datos que desea';
$string['ai:sqldescription'] = 'Selecciona {$a->columns} de {$a->tables}.';
$string['ai:sqldescriptionnocols'] = 'Informe sobre {$a}.';
$string['ai:sqlname'] = 'Informe de {$a}';
$string['audienceallusers'] = 'Todos los usuarios del sitio';
$string['audiencecohort'] = 'Miembros de cohortes';
$string['audiencecohorts'] = 'Cohortes';
$string['audiencecoursemissing'] = 'un curso eliminado';
$string['audiencecourseparticipant'] = 'Participantes del curso';
$string['audiencecourseparticipantdesc'] = 'Usuarios con una matrícula activa en {$a}.';
$string['audiencecourserole'] = 'Usuarios con un rol en el curso';
$string['audiencecourseroledesc'] = 'Usuarios que ocupan alguno de los roles elegidos en {$a} (o en un contexto ancestro).';
$string['audiencedefault'] = 'Automático (según el curso y la visibilidad)';
$string['audiencenone'] = 'Nadie (solo usted y los gestores del sitio)';
$string['audienceroles'] = 'Roles';
$string['audiencesettings'] = 'Quién puede ver el informe';
$string['audiencetype'] = 'Audiencia';
$string['audiencetype_help'] = 'Controla quién puede abrir el informe de Report Builder publicado.

* **Automático** — se deriva de los ajustes anteriores: un informe con ámbito de curso se muestra a los participantes de ese curso, un informe de todo el sitio a todos los usuarios, y un informe oculto solo a usted y a los gestores del sitio.
* **Participantes del curso / Usuarios con un rol en el curso** — requieren que se haya establecido un ámbito de curso más arriba.
* **Todos los usuarios del sitio**, **Miembros de cohortes**, **Nadie** — se aplican a todo el sitio.

Puede refinar aún más la audiencia en la pestaña Audiencias de Report Builder, pero volver a publicar el informe la restablece a esta elección.';
$string['bulkactions'] = 'Acciones masivas';
$string['cachedef_schema'] = 'Esquema de la base de datos y mapa de claves foráneas para el autocompletado del editor';
$string['cacheheader'] = 'Caché';
$string['cachemode'] = 'Modo de caché';
$string['cachemode_help'] = 'El modo en vivo ejecuta el SQL directamente en cada solicitud. El modo en caché lo ejecuta según una programación y sirve la última instantánea actualizada — utilice esto para una consulta lenta, de modo que los usuarios obtengan resultados rápidos y la base de datos no se consulte en cada carga de página.';
$string['cachemodelive'] = 'En vivo';
$string['cachemodecached'] = 'En caché';
$string['cachemodecachedbadge'] = 'En caché · cada {$a} min';
$string['cacheinterval'] = 'Intervalo de actualización';
$string['cacheinterval_help'] = 'Con qué frecuencia se actualiza la tabla de caché en segundo plano. Los intervalos más cortos mantienen los datos más frescos, pero ejecutan la consulta (lenta) con más frecuencia.';
$string['cacheintervaloption'] = 'Cada {$a} minutos';
$string['cachedataasof'] = 'Datos a fecha de: {$a}';
$string['cachedataasoflabel'] = 'Datos a fecha de';
$string['cachenodata'] = 'Aún no actualizado — la primera actualización se ejecuta poco después de publicar.';
$string['cachelasterrorlabel'] = 'Último error de actualización: {$a}';
$string['errcacheintervalempty'] = 'Introduzca un intervalo de actualización de al menos 5 minutos.';
$string['errcreatecachetable'] = '{$a}';
$string['errdropcachetable'] = 'No se pudo eliminar la tabla de caché: {$a}';
$string['errrefreshcache'] = 'No se pudo actualizar la tabla de caché: {$a}';
$string['cacherefreshed'] = 'Caché actualizada.';
$string['refreshcachenow'] = 'Actualizar caché ahora';
$string['task:scanduerefreshes'] = 'Actualizar las cachés pendientes de los informes SQL en modo caché';
$string['chartbar'] = 'Gráfico de barras';
$string['chartcolumn'] = 'Gráfico';
$string['chartdatalabels'] = 'Mostrar etiquetas de valor';
$string['chartdatalabels_help'] = 'Dibuja cada valor representado como un número sobre su barra o junto a su punto de línea, de modo que las cifras exactas puedan leerse directamente en el gráfico. Se aplica solo a gráficos de barras y de líneas (los gráficos circulares y de anillo muestran los valores en la leyenda). Se suprime automáticamente cuando una serie tiene demasiados puntos para etiquetarlos sin solaparse. Desactivado de forma predeterminada.';
$string['chartdatalabelslabel'] = 'Imprimir cada valor en el gráfico de barras o líneas';
$string['chartdoughnut'] = 'Gráfico de anillo';
$string['chartdownloadpng'] = 'Descargar PNG';
$string['chartexportcsv'] = 'Exportar CSV';
$string['chartlabelsize'] = 'Tamaño de texto de las etiquetas';
$string['chartlabelsize_help'] = 'Tamaño de fuente (en puntos) para las etiquetas de categoría del gráfico — la leyenda circular y las etiquetas del eje X de barras / líneas.';
$string['chartlabelsizeoption'] = '{$a} pt';
$string['chartline'] = 'Gráfico de líneas';
$string['chartmulticolour'] = 'Barras multicolor';
$string['chartmulticolour_help'] = 'Da a cada barra su propio color de una paleta apta para daltónicos en lugar de un color compartido, de modo que las categorías sean más fáciles de diferenciar. Solo para gráficos de barras (los circulares y de anillo ya tienen color por porción; una línea es una única serie). Desactivado de forma predeterminada.';
$string['chartmulticolourlabel'] = 'Colorear cada barra de forma diferente';
$string['chartnone'] = 'Sin gráfico';
$string['chartpie'] = 'Gráfico circular';
$string['chartprint'] = 'Imprimir';
$string['chartpublishrequired'] = 'Para configurar el gráfico, pulse Publicar y continuar editando.';
$string['chartreportname'] = '{$a} (gráfico)';
$string['chartrowlimit'] = 'Límite de filas del gráfico';
$string['chartrowlimit_help'] = 'Número máximo de filas a representar. Manténgalo bajo (≤ 200) para gráficos legibles.';
$string['chartsettings'] = 'Ajustes del gráfico';
$string['chartshowdata'] = 'Mostrar tabla de datos';
$string['chartshowdata_help'] = 'Muestra los pares de etiqueta y valor del gráfico como una tabla debajo de la imagen del gráfico en el informe de gráfico. Proporciona una alternativa textual para lectores de pantalla y permite a los usuarios leer las cifras exactas. Desactivado de forma predeterminada.';
$string['chartshowdatalabel'] = 'Mostrar los valores representados como una tabla debajo del gráfico';
$string['charttype'] = 'Tipo de gráfico';
$string['chartxcol'] = 'Columna de etiqueta (eje X / porciones)';
$string['chartxcol_help'] = 'Columna cuyos valores etiquetan cada barra, punto o porción del gráfico circular.';
$string['chartycol'] = 'Columna de valor (eje Y)';
$string['chartycol_help'] = 'Columna cuyos valores se representan. Debe contener datos numéricos.';
$string['checkallgood'] = 'No se encontraron problemas. La consulta parece correcta.';
$string['checkcasecolumnsintro'] = 'Estas columnas aplican UPPER()/LOWER() en SQL:';
$string['checkcasecolumnsintroone'] = 'Esta columna aplica UPPER()/LOWER() en SQL:';
$string['checkcasecolumnsmanual'] = 'No se pudo localizar automáticamente la expresión de esta columna — cámbiela a %%CASE()%% manualmente.';
$string['checkcasecolumnsoutro'] = 'Haga clic en el nombre para cambiarla a %%CASE()%%, de modo que la capitalización se aplique al mostrarse mientras la columna sigue ordenándose y filtrándose por el valor original (y se mantiene portable entre bases de datos).';
$string['checkdatecolumnsintro'] = 'Estas columnas parecen fechas:';
$string['checkdatecolumnsintroone'] = 'Esta columna parece una fecha:';
$string['checkdatecolumnsmanual'] = 'No se pudo localizar automáticamente la expresión de esta columna — envuélvala en %%TIMESTAMP()%% manualmente.';
$string['checkdatecolumnsoutro'] = 'Haga clic en el nombre para envolver su expresión en %%TIMESTAMP()%%, de modo que se muestre como una fecha formateada y ordenable.';
$string['checkdistinctlarge'] = 'SELECT DISTINCT sobre {$a} filas debe ordenar y eliminar duplicados de todo el resultado, lo cual es lento con este tamaño. Considere usar GROUP BY sobre columnas indexadas, o elimine DISTINCT si las combinaciones (joins) ya producen filas únicas.';
$string['checkfullscan'] = 'Recorrido completo de la tabla "{$a->table}" (~{$a->rows} filas), sin usar ningún índice. Este informe puede ser lento — añada un filtro WHERE sobre una columna indexada. Columnas indexadas: {$a->indexed}.';
$string['checkindexedcolumns'] = 'Indexadas: {$a}.';
$string['checknotindexedcolumns'] = 'No indexadas: {$a}.';
$string['indexedcolumn'] = 'Columna indexada';
$string['checklargeresult'] = 'Esta consulta devuelve {$a} filas. Los resultados grandes se renderizan lentamente — añada un filtro o un LIMIT.';
$string['checkleadingwildcard'] = 'Un patrón LIKE comienza con un comodín ("%…" o "_…"). Un comodín inicial impide que la base de datos use un índice en esa columna, forzando un recorrido completo. Ancle el patrón ("abc%") siempre que sea posible.';
$string['checknonsargable'] = 'Una función envuelve una columna en la cláusula WHERE (p. ej. DATE(col) o LOWER(col)). Esto no es "sargable" — la base de datos no puede usar un índice en esa columna. Filtre la columna sin transformar (p. ej. una comparación de rango, o compare un epoch almacenado).';
$string['checkquery'] = 'Probar consulta';
$string['checkquery_help'] = 'Ejecuta su SQL contra la base de datos sin guardar ni publicar, y le informa del resultado. Comprueba que la consulta es válida y se ejecuta, cuenta las filas que devuelve, y señala posibles problemas de rendimiento — recorridos completos de tabla, índices ausentes, filtros no "sargables", conjuntos de resultados grandes o DISTINCT — además de columnas de fecha que quizá quiera envolver en %%TIMESTAMP()%%.

Esto es solo informativo: nunca modifica sus datos y no es obligatorio antes de guardar o publicar.';
$string['checkrowcount'] = 'Filas devueltas: {$a}.';
$string['checkrowcounttimed'] = 'Filas devueltas: {$a->rows}. Generado en {$a->ms} ms.';
$string['checkrowcounttimeout'] = 'El conteo de filas superó el tiempo de espera tras {$a}s — la consulta es lenta o el resultado muy grande. El informe puede ser lento.';
$string['checkselectsubquery'] = 'Una subconsulta en la lista SELECT se evalúa una vez por cada fila devuelta, multiplicando el trabajo en un resultado grande. Un JOIN o un WITH (CTE) suele ser más rápido.';
$string['checksortindex'] = 'El informe se ordena por {$a->sortcol}, que no está indexada, por lo que la base de datos ordena todo el resultado. Ordenar por una columna indexada es más rápido — columnas indexadas disponibles: {$a->indexed}.';
$string['compiledsql'] = 'SQL compilado (lo que realmente se ejecutó)';
$string['confirmdeletemany'] = '¿Está seguro de que desea eliminar estas {$a} fuente(s) de informe? Esto elimina la vista y el informe subyacentes de cada una y no se puede deshacer.';
$string['convertaliasspaces'] = 'Reemplazar automáticamente los espacios del alias de columna por guiones bajos';
$string['convertquestionmark'] = 'Convertir automáticamente ? dentro de comillas a CHAR(63)';
$string['copyof'] = 'Copia de {$a}';
$string['copysuccess'] = 'Fuente de informe copiada. Ahora está editando la copia.';
$string['coursecolumn'] = 'Restringir a los cursos que imparte el usuario';
$string['coursecolumn_help'] = 'Opcionalmente, delimite este informe para que cada usuario vea solo las filas de los cursos que imparte. Elija la columna de salida que contiene un id de curso; en el momento de la vista, el informe mostrará solo las filas donde esa columna sea uno de los cursos en los que el usuario tiene un rol de profesor o profesor sin permiso de edición.

Un usuario que no imparte ningún curso no verá ninguna fila. Esto le permite publicar un único informe para una audiencia amplia (por ejemplo, todo el personal) mientras cada profesor sigue viendo solo sus propios cursos. Deje "Elegir una columna…" para no aplicar filtro por curso del profesor.';
$string['coursescope'] = 'Ámbito del curso';
$string['coursescope_help'] = 'El curso al que pertenece este informe. Déjelo vacío para un informe de todo el sitio.

El curso determina dos cosas al publicar el informe: el contexto en el que se comprueba su permiso "Ver informe", y su audiencia predeterminada (participantes del curso para un informe con ámbito de curso, todos los usuarios para uno de todo el sitio).

Cambie esto para redelimitar una consulta — por ejemplo, un borrador importado que se estableció como de todo el sitio porque su curso original no existía en este sitio. Solo puede elegir cursos en los que tenga permiso para ver informes.';
$string['createrole:aigenerate'] = 'Incluir "Generación de SQL con IA"';
$string['createrole:aigenerate_desc'] = 'Conceder también local/sqlchat:use, de modo que los titulares puedan usar el cuadro de preguntas de IA para generar SQL. Solo se muestra cuando el plugin local_sqlchat está instalado. Déjelo sin marcar si los autores deben escribir el SQL ellos mismos.';
$string['createrole:approve'] = 'Incluir "Aprobar y publicar"';
$string['createrole:approve_desc'] = 'Conceder también report/sql:approve, de modo que los titulares puedan publicar y despublicar fuentes de informe ellos mismos. Déjelo sin marcar si un aprobador independiente debe publicar sus borradores.';
$string['createrole:author'] = 'Crear fuentes de informe';
$string['createrole:author_desc'] = 'Siempre incluido: report/sql:author permite a los titulares escribir y guardar fuentes de informe (el propósito del rol). También se conceden siempre moodle/reportbuilder:view, moodle/reportbuilder:viewall y moodle/reportbuilder:editall, de modo que los titulares puedan abrir y editar cualquier informe publicado en /reportbuilder/view.php sin importar su audiencia o propietario.';
$string['createrole:create'] = 'Crear rol';
$string['createrole:done'] = 'Se creó el rol "Autor de informes". Asigne personas a continuación.';
$string['createrole:exists'] = 'Ya existe un rol "Autor de informes". Al enviar este formulario se actualizarán sus permisos según su selección de abajo.';
$string['createrole:intro'] = 'Esto crea un rol de nivel de sistema que agrupa los permisos de fuentes de informe, de modo que pueda permitir que administradores no de confianza total autoren informes sin convertirlos en gestores completos del sitio. Elija qué permisos incluir, y luego cree el rol y asigne personas.';
$string['createrole:linklabel'] = 'Crear el rol "Autor de informes"';
$string['createrole:title'] = 'Crear el rol "Autor de informes"';
$string['createrole:updated'] = 'Se actualizaron los permisos del rol "Autor de informes". Asigne personas a continuación.';
$string['createrole:viewall'] = 'Incluir "Ver todas las fuentes de informe"';
$string['createrole:viewall_desc'] = 'Conceder también report/sql:viewall, de modo que los titulares puedan ver y gestionar las fuentes de informe de todos, no solo las propias.';
$string['createrole:warning'] = 'Crear un informe implica escribir una consulta SQL SELECT arbitraria, que puede leer casi cualquier tabla de la base de datos (solo se bloquea una pequeña lista de exclusión como las tablas config, sessions y de contraseñas). Este rol constituye, por tanto, efectivamente una concesión de lectura de datos de todo el sitio. Asígnelo solo a personas en las que confiaría con acceso de lectura directo a la base de datos, y confirme que las columnas sensibles están cubiertas por la lista de exclusión de columnas en los ajustes del plugin.';
$string['crimport:colname'] = 'Informe';
$string['crimport:colnotes'] = 'Cambios aplicados';
$string['crimport:colreason'] = 'Motivo';
$string['crimport:coltype'] = 'Tipo';
$string['crimport:importableheading'] = 'Informes importables';
$string['crimport:importselected'] = 'Importar seleccionados';
$string['crimport:intro'] = 'Estos son los informes SQL encontrados en el bloque Configurable Reports. Los informes importables se traducen sin problemas y se crearán como borradores propiedad de usted, listos para publicar. Los informes rechazados usan funciones que no se pueden convertir automáticamente — impórtelos manualmente.';
$string['crimport:linklabel'] = 'Importar desde Configurable Reports';
$string['crimport:noneimportable'] = 'No se pudo traducir automáticamente ningún informe SQL de Configurable Reports. Consulte la lista de rechazados a continuación para conocer el motivo.';
$string['crimport:noneselected'] = 'No se seleccionó ningún informe.';
$string['crimport:noteclean'] = 'No se necesitaron cambios';
$string['crimport:notedatefn'] = 'Se reescribieron las funciones de fecha de MySQL a los tokens portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%';
$string['crimport:notenativedate'] = 'Se conservaron las funciones de fecha nativas de MySQL {$a} — se ejecutan en esta base de datos MySQL/MariaDB, pero el informe importado no será portable a PostgreSQL';
$string['crimport:noteqmark'] = 'Se reescribió el carácter literal ? en una cadena como chr(63)';
$string['crimport:notequotes'] = 'Se convirtieron los literales de cadena con "comillas dobles" a \'comillas simples\'';
$string['crimport:notetoken'] = 'Se sustituyó el token de Configurable Reports {$a}';
$string['crimport:reasondatefn'] = 'Usa la función de fecha exclusiva de MySQL {$a}, que no tiene un equivalente portable';
$string['crimport:reasonfilter'] = 'Usa un token de filtro interactivo {$a}; reconstrúyalo como un filtro de Report Builder tras la importación';
$string['crimport:reasonnosql'] = 'No se pudo decodificar SQL alguno de este informe';
$string['crimport:reasonnotsql'] = 'No es un informe SQL (tipo: {$a})';
$string['crimport:reasontoken'] = 'Usa un token no compatible {$a}';
$string['crimport:reasonuserid'] = 'Usa {$a}; utilice en su lugar el ajuste "Restringir al usuario que visualiza" en el borrador importado';
$string['crimport:rejectedheading'] = 'Informes rechazados';
$string['crimport:title'] = 'Importar desde Configurable Reports';
$string['crimport:title_help'] = 'Importa los informes SQL almacenados en el bloque Configurable Reports (block_configurable_reports) como fuentes de informe en borrador.

Cada informe se decodifica y se somete a una traducción fija: las funciones de fecha de MySQL se convierten en tokens portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%, las cadenas con comillas dobles se convierten en comillas simples, y un carácter literal ? en una cadena se reconstruye con chr(63). Los informes que usan funciones que no se pueden convertir (como %%USERID%% o tokens interactivos %%FILTER%%) se listan como rechazados con un motivo.

Los informes importados quedan como borradores propiedad de usted y deben publicarse antes de estar activos. No se usa IA — cada conversión es una regla fija.';
$string['crimport:unavailable'] = 'El bloque Configurable Reports (block_configurable_reports) no está instalado, por lo que no hay nada que importar.';
$string['customisecolumns'] = 'Personalizar informe';
$string['customsqlimport:intro'] = 'Estas son las consultas encontradas en el informe Ad-hoc Database Queries (report_customsql). Las consultas importables se traducen sin problemas y se crearán como borradores propiedad de usted, listos para publicar. Las consultas rechazadas usan funciones que no se pueden convertir automáticamente — impórtelas manualmente.';
$string['customsqlimport:linklabel'] = 'Importar desde Ad-hoc Database Queries';
$string['customsqlimport:noneimportable'] = 'No se pudo traducir automáticamente ninguna consulta de Ad-hoc Database Queries. Consulte la lista de rechazadas a continuación para conocer el motivo.';
$string['customsqlimport:noteescape'] = 'Se sustituyeron los tokens de escape de customsql (%%Q%% / %%C%% / %%S%%) por sus caracteres literales';
$string['customsqlimport:reasonparam'] = 'Usa el parámetro con nombre interactivo {$a}; reconstrúyalo como un filtro de Report Builder tras la importación';
$string['customsqlimport:title'] = 'Importar desde Ad-hoc Database Queries';
$string['customsqlimport:title_help'] = 'Importa las consultas almacenadas en el informe Ad-hoc Database Queries (report_customsql) como fuentes de informe en borrador.

Cada consulta se somete a una traducción fija: las funciones de fecha de MySQL se convierten en tokens portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%, las cadenas con comillas dobles se convierten en comillas simples, los tokens de escape de customsql (%%Q%% / %%C%% / %%S%%) se convierten en sus caracteres literales, y un carácter literal ? en una cadena se reconstruye con chr(63). Las consultas que usan funciones que no se pueden convertir (como %%USERID%% o parámetros interactivos :con nombre) se listan como rechazadas con un motivo.

Las consultas importadas quedan como borradores propiedad de usted y deben publicarse antes de estar activas. customsql no tiene ámbito por curso, por lo que todo borrador comienza siendo de todo el sitio. No se usa IA — cada conversión es una regla fija.';
$string['customsqlimport:unavailable'] = 'El informe Ad-hoc Database Queries (report_customsql) no está instalado, por lo que no hay nada que importar.';
$string['delete'] = 'Eliminar';
$string['deleteselected'] = 'Eliminar seleccionados';
$string['deleteselecthelp'] = 'Marque las fuentes de informe a eliminar. Eliminar borra la vista y el informe de base de datos subyacentes de cada una y no se puede deshacer.';
$string['description'] = 'Descripción';
$string['duplicate'] = 'Duplicar';
$string['edit'] = 'Editar';
$string['editreport'] = 'Editar en Report Builder';
$string['embedcodecopied'] = 'Código de inserción copiado';
$string['embedcodecopy'] = 'Copiar código de inserción';
$string['entityquery'] = 'Fuente de informe';
$string['erraliasspaces'] = 'El alias de columna "{$a}" contiene espacios. Los alias de columna en SQL Report no pueden tener espacios — use un guion bajo o camelCase en su lugar, p. ej. SELECT firstname AS first_name FROM user. Puede renombrar la columna con un espacio después de publicar, mediante Report Builder.';
$string['erraudiencecohortsempty'] = 'Elija al menos una cohorte.';
$string['erraudiencecourse'] = 'Esta audiencia se aplica a un curso. Elija un ámbito de curso más arriba antes de seleccionarla.';
$string['erraudiencerolesempty'] = 'Elija al menos un rol.';
$string['errchartdata'] = 'No se pudieron cargar los datos del informe para este gráfico. Contacte con el propietario del informe si el problema persiste.';
$string['errchartnotconfigured'] = 'No hay ningún gráfico configurado para esta consulta. Edite la consulta para añadir los ajustes del gráfico.';
$string['errchartnotpublished'] = 'Esta consulta no está publicada. Publíquela primero antes de ver el gráfico.';
$string['errcolumnnoalias'] = 'La columna "{$a}" es una expresión sin nombre. Dé un alias a cada columna calculada o agregada, p. ej. SELECT count(*) AS total FROM course.';
$string['errcourseidplaceholder'] = 'El SQL usa %%COURSEID%%, por lo que este informe necesita un ámbito de curso fijo. Elija un curso más arriba antes de guardar — o, para mostrar a cada curso sus propios datos en un bloque, elimine el filtro %%COURSEID%% del SQL, muestre la columna de id de curso, y establezca en su lugar "Restringir al curso donde está el bloque".';
$string['errcreateview'] = '{$a}';
$string['errdeniedcolumn'] = 'Columna no permitida: {$a}';
$string['errdeniedkeyword'] = 'Palabra clave no permitida: {$a}';
$string['errdeniedtable'] = 'Tabla no permitida: {$a}';
$string['errdropview'] = 'No se pudo eliminar la vista de base de datos: {$a}';
$string['errduplicatecolumn'] = 'Las tablas combinadas comparten nombres de columna duplicados (por ejemplo, ambas tienen "id"). Reemplace SELECT * por alias de columna explícitos: SELECT u.id AS userid, fp.id AS postid, ...';
$string['errimportempty'] = 'El archivo de exportación no contiene fuentes de informe.';
$string['errimportformat'] = 'Este archivo no es una exportación válida de SQL Report.';
$string['errjoinnoon'] = 'A un JOIN le falta su condición ON (o USING). Cada JOIN necesita una condición de combinación, p. ej. JOIN {user_enrolments} ue ON ue.userid = u.id';
$string['errmultistatement'] = 'No se permiten varias sentencias.';
$string['errnodeleteselection'] = 'Seleccione al menos una fuente de informe para eliminar.';
$string['errnoexportselection'] = 'Seleccione al menos una fuente de informe para exportar.';
$string['errnoimportselection'] = 'Seleccione al menos una fuente de informe para importar.';
$string['errnotselect'] = 'Solo se permiten consultas SELECT.';
$string['errpagecourseambiguous'] = 'Un informe solo puede llevar un token %%PAGECOURSE(expr)%%, y su expresión debe resolverse a una columna de salida con nombre. Use un único %%PAGECOURSE()%% marcando la columna que contiene un id de curso, y déle un alias, p. ej. SELECT %%PAGECOURSE(c.id)%% AS courseid, ... FROM {course} c.';
$string['errpagecourseunresolved'] = 'El token %%PAGECOURSE()%% no pudo asociarse a ninguna columna de salida llamada "{$a}". Dé a la columna marcada un alias explícito para que se convierta en una columna de vista con nombre, p. ej. SELECT %%PAGECOURSE(c.id)%% AS courseid, ... — se detuvo la publicación en lugar de dejar el filtro de curso de página sin aplicar.';
$string['errparse'] = 'No se pudo analizar el SQL: {$a}';
$string['errpgsqldatefn'] = 'La función exclusiva de PostgreSQL {$a} no es compatible con MySQL. Use un equivalente multiplataforma.';
$string['errplaceholder'] = 'El SQL contiene un marcador de posición sin rellenar "{$a}". Reemplácelo por un valor real antes de guardar — p. ej. cambie "l.userid = ##" por "l.userid = 2".';
$string['errplaceholderuserid'] = 'El SQL contiene "{$a}", que no es un marcador de posición compatible. No existe un marcador de posición por usuario, porque el informe se ejecuta desde una vista de base de datos fija. Para restringir el informe a las filas de quien lo abra, envuelva la columna de id de usuario en el SQL con %%VIEWER(...)%% — p. ej. SELECT %%VIEWER(u.id)%% AS viewerid, ... — o elimine "{$a}" y seleccione la columna de id de usuario en el campo "Restringir al usuario que visualiza" al final de este formulario. En cualquier caso, el filtro por usuario se aplica automáticamente en tiempo de ejecución.';
$string['errqualifiedtable'] = 'No se permite la referencia a tabla cualificada por esquema "{$a}". Los informes solo pueden leer las propias tablas del sitio usando la sintaxis {tablename} de Moodle; se bloquean las referencias entre esquemas o entre bases de datos (p. ej. information_schema.columns).';
$string['errquestionmark'] = 'El SQL contiene un carácter ?, que la capa de base de datos trata como marcador de parámetro de consulta. Si ? aparece dentro de una cadena URL, reemplácelo por CHAR(63) — p. ej. CONCAT(\'…/view.php\', CHAR(63), \'id=\', course.id).';
$string['errteachesambiguous'] = 'Un informe solo puede llevar un token %%TEACHES(expr)%%, y su expresión debe resolverse a una columna de salida con nombre. Use un único %%TEACHES()%% marcando la columna que contiene un id de curso, y déle un alias, p. ej. SELECT %%TEACHES(c.id)%% AS courseid, ... FROM {course} c.';
$string['errteachesunresolved'] = 'El token %%TEACHES()%% no pudo asociarse a ninguna columna de salida llamada "{$a}". Dé a la columna marcada un alias explícito para que se convierta en una columna de vista con nombre, p. ej. SELECT %%TEACHES(c.id)%% AS courseid, ... — se detuvo la publicación en lugar de dejar el filtro de curso del profesor sin aplicar.';
$string['errviewerambiguous'] = 'Un informe solo puede llevar un token %%VIEWER(expr)%%, y su expresión debe resolverse a una columna de salida con nombre. Use un único %%VIEWER()%% marcando la columna que contiene el id de usuario, y déle un alias, p. ej. SELECT %%VIEWER(fp.userid)%% AS viewerid, fp.subject FROM {forum_posts} fp.';
$string['errviewerunresolved'] = 'El token %%VIEWER()%% no pudo asociarse a ninguna columna de salida llamada "{$a}". Dé a la columna marcada un alias explícito para que se convierta en una columna de vista con nombre, p. ej. SELECT %%VIEWER(u.id)%% AS viewerid, ... — se detuvo la publicación en lugar de dejar el informe sin delimitar (lo que mostraría todas las filas a todos los usuarios).';
$string['event:querycreated'] = 'Consulta ad-hoc creada';
$string['event:querydeleted'] = 'Consulta ad-hoc eliminada';
$string['event:querypublished'] = 'Consulta ad-hoc publicada';
$string['event:queryunpublished'] = 'Consulta ad-hoc despublicada';
$string['event:queryupdated'] = 'Consulta ad-hoc actualizada';
$string['export'] = 'Exportar';
$string['exportselected'] = 'Exportar seleccionados';
$string['exportselecthelp'] = 'Marque las fuentes de informe a incluir en el archivo de exportación, y luego descargue el JSON.';
$string['filterpublishrequired'] = 'Para configurar los filtros por usuario y por curso, pulse Publicar y continuar editando.';
$string['copysql'] = 'Copiar SQL';
$string['copysqldone'] = 'SQL copiado al portapapeles';
$string['copysqltooltip'] = 'Copiar el SQL al portapapeles';
$string['copysqlprefixed'] = 'Copiar SQL con nombres de tabla reales';
$string['copysqlprefixeddone'] = 'SQL copiado con nombres de tabla reales y con prefijo';
$string['copysqlmenu'] = 'Opciones de copia';
$string['errcopyprefixed'] = 'No se pudo copiar el SQL con prefijo.';
$string['formatsql'] = 'Formatear SQL';
$string['formatsqltooltip'] = 'Reformatear el SQL al diseño estándar (Mayús+Ctrl+F)';
$string['import'] = 'Importar';
$string['importdemoted'] = 'Establecido como de todo el sitio porque su curso no se encontró en este sitio. Edite cada borrador y establezca su ámbito de curso antes de publicar: {$a}.';
$string['importdone'] = 'Se importaron {$a} fuente(s) de informe como borradores.';
$string['importallhidden'] = 'No se puede importar nada: todas las fuentes de informe de este archivo necesitan un plugin que no está instalado aquí: {$a}.';
$string['importfile'] = 'Archivo de exportación';
$string['importhidden'] = 'Oculto (falta un plugin requerido en este sitio): {$a}.';
$string['importselected'] = 'Importar seleccionados';
$string['importselecthelp'] = 'Marque las fuentes de informe a importar. Cada una se crea como un nuevo borrador propiedad de usted y debe publicarse antes de usarse.';
$string['importskipped'] = 'Omitido (falló la validación de SQL): {$a}.';
$string['importupload'] = 'Cargar y elegir';
$string['importuploadhelp'] = 'Cargue un archivo JSON generado previamente por la acción Exportar. A continuación, elegirá qué fuentes de informe importar.';
$string['install:createrole'] = 'Opcionalmente cree un rol "Autor de informes" para que los administradores no globales puedan crear informes. Revise antes las implicaciones de seguridad: {$a}';
$string['install:loadsamples'] = 'SQL Report incluye informes SQL de ejemplo que puede cargar para empezar: {$a}';
$string['install:privilegefail'] = 'SQL Report se instaló, pero el usuario de la base de datos no puede crear ni eliminar vistas. Publicar consultas fallará hasta que se corrijan los permisos. Error: {$a}';
$string['install:privilegeok'] = 'SQL Report: el usuario de la base de datos puede crear y eliminar vistas.';
$string['lastmodified'] = 'Última modificación';
$string['linkargexpr'] = 'El valor de columna que se muestra en la celda.';
$string['linkargkeycol'] = 'Opcional: otra columna de salida cuyo valor rellena <code>{}</code>, de modo que la celda pueda mostrar una cosa mientras el enlace se basa en otra.';
$string['linkargpath'] = "URL relativa al sitio (debe comenzar con <code>/</code>, sin esquema); <code>{}</code> se reemplaza por el valor codificado para URL, p. ej. <code>/user/view.php?id={}</code>.";
$string['linkhelpargs'] = 'Argumentos';
$string['linkhelpintro'] = 'Renderiza una celda como un enlace: <code>%%LINK(expr, \'path\')%%</code> o <code>%%LINK(expr, keycol, \'path\')%%</code>.';
$string['linkhelptitle'] = 'Token de enlace';
$string['name'] = 'Nombre';
$string['noqueries'] = 'Aún no hay fuentes de informe.';
$string['norows'] = 'No hay datos para mostrar.';
$string['owner'] = 'Propietario';
$string['pagecoursecolumn'] = 'Restringir al curso donde está el bloque';
$string['pagecoursecolumn_help'] = 'Se aplica solo cuando este informe se muestra a través del bloque SQL Report en una página de curso. Elija la columna de salida que contiene un id de curso; el bloque mostrará entonces solo las filas del curso de la página en la que se encuentra, de modo que un bloque (o un bloque añadido a todos los cursos) muestre a cada curso sus propios datos.

Fuera de una página de curso (Panel o la portada del sitio) no se aplica ningún filtro de curso de página. El visor de informes independiente también ignora esto, ya que no tiene "curso actual". Deje "Elegir una columna…" para no aplicar filtro de curso de página.';
$string['plugindisabled'] = 'SQL Report está actualmente desactivado por el administrador del sitio.';
$string['pluginexplained'] = 'Acerca de las fuentes de informe';
$string['pluginexplained_help'] = 'Este plugin le permite escribir una consulta SQL SELECT y publicarla como un informe de Report Builder totalmente configurable — sin necesidad de PHP.

Al publicar una consulta, el plugin crea una VISTA de base de datos a partir de su SQL, lee sus columnas, y registra una fuente de datos de Report Builder que apunta a esa vista. Después puede construir, filtrar y compartir el informe como cualquier otro informe de Report Builder.

Solo se permiten consultas SELECT, y una lista de exclusión bloquea el acceso a tablas sensibles. Editar el SQL de una consulta publicada reconstruye la vista y el informe en la siguiente publicación.';
$string['pluginname'] = 'SQL Report';
$string['preview'] = 'Vista previa de las primeras 5 filas';
$string['preview_help'] = 'Renderiza su SQL actual como un informe real de Report Builder, en línea y sin guardar ni publicar. Las columnas se tipan y formatean exactamente como lo estarían al publicar, así que esta es una forma rápida de ver cómo quedará el informe. Solo se muestran las primeras 5 filas.';
$string['previewheading'] = 'Resultado de la vista previa';
$string['previewloading'] = 'Generando vista previa…';
$string['privacy:metadata:query'] = 'Fuentes de informe guardadas y creadas por los usuarios.';
$string['privacy:metadata:query:ownerid'] = 'Usuario que creó la consulta.';
$string['privacy:metadata:query:querysql'] = 'El SQL de la consulta.';
$string['privacy:metadata:query:timecreated'] = 'Cuándo se creó la consulta.';
$string['privacy:metadata:queryview'] = 'Un registro de qué fuentes de informe publicadas ha abierto cada usuario.';
$string['privacy:metadata:queryview:timeviewed'] = 'Cuándo se abrió la fuente de informe.';
$string['privacy:metadata:queryview:userid'] = 'Usuario que abrió la fuente de informe.';
$string['publish'] = 'Publicar';
$string['queries'] = 'Informes SQL guardados';
$string['querysql'] = 'SQL (solo SELECT)';
$string['querysql_help'] = 'Una única sentencia SELECT o WITH...SELECT. Use la sintaxis de tabla de Moodle (p. ej. {course}). El plugin crea una VISTA de base de datos a partir de esta consulta y expone sus columnas como una fuente de Report Builder.

Ponga siempre alias a las tablas (p. ej. FROM {user} u), ya que {user} se resuelve a mdl_user en tiempo de ejecución.

Envuelva una columna de texto en %%CASE(expr, mode)%% para mostrarla en mayúsculas, minúsculas, tipo título o tipo oración (p. ej. %%CASE(u.lastname, upper)%%). El valor almacenado no cambia, así que la columna sigue ordenándose y filtrándose por el texto original, y la transformación funciona igual en MySQL/MariaDB y PostgreSQL.

Para el esquema de base de datos de Moodle, consulte <a href="https://www.examulator.com/er/output/index.html" target="_blank">examulator.com/er</a>.

Para consultas de ejemplo e inspiración, consulte <a href="https://docs.moodle.org/502/en/ad-hoc_contributed_reports" target="_blank">Moodle ad-hoc contributed reports</a>.';
$string['reportsource'] = 'Fuente de informe';
$string['reportsourceheader'] = '{$a}';
$string['reportsources'] = 'Informes SQL';
$string['repository:intro'] = 'Estas fuentes de informe compartidas provienen del repositorio remoto {$a}. Marque las que desee e impórtelas. Cada una se crea como un borrador propiedad de usted que debe publicar antes de usarlo.';
$string['repository:introsingle'] = 'Estas fuentes de informe compartidas provienen del repositorio remoto {$a}. Elija una para importar. Se crea como un borrador propiedad de usted, con el prefijo "Sample:" en el nombre, que debe publicar antes de usarlo.';
$string['repository:linklabel'] = 'Importar desde repositorio compartido';
$string['repository:none'] = 'No se pudo leer ninguna fuente de informe compartida del repositorio {$a}. Compruebe la URL y que el repositorio contiene archivos de exportación de fuentes de informe.';
$string['repository:noneselected'] = 'No se seleccionó ninguna fuente de informe.';
$string['repository:refresh'] = 'Actualizar desde el repositorio';
$string['repository:title'] = 'Fuentes de informe del repositorio compartido';
$string['repository:titlesingle'] = 'Importar una fuente de informe compartida';
$string['repository:unconfigured'] = 'No hay ningún repositorio compartido configurado. Establezca uno primero en los ajustes del plugin (Repositorio de fuentes de informe compartido).';
$string['roledescription'] = 'Crear, editar y publicar fuentes de informe (report_sql) en todo el sitio. NOTA: crear informes permite consultas SQL SELECT arbitrarias contra la base de datos, por lo que este rol otorga, en la práctica, lectura de datos de todo el sitio. Asígnelo solo a creadores de informes de confianza.';
$string['rolename'] = 'Autor de informes';
$string['runreport'] = 'Abrir informe';
$string['samples:coldesc'] = 'Descripción';
$string['samples:colname'] = 'Nombre';
$string['samples:colselect'] = 'Importar';
$string['samples:duplicates'] = 'Omitido (ya existe): {$a}.';
$string['samples:import'] = 'Importar';
$string['samples:importselected'] = 'Importar seleccionados';
$string['samples:intro'] = '{$a} fuentes de informe de ejemplo vienen incluidas con este plugin. Marque las que desee e impórtelas. Cada una se crea como un borrador propiedad de usted que debe publicar antes de usarlo.';
$string['samples:introsingle'] = '{$a} fuentes de informe de ejemplo vienen incluidas con este plugin. Elija una para importar. Se crea como un borrador propiedad de usted, con el prefijo "Sample:" en el nombre, que debe publicar antes de usarlo.';
$string['samples:linklabel'] = 'Cargar informes SQL de ejemplo';
$string['samples:none'] = 'No se encontraron fuentes de informe de ejemplo incluidas.';
$string['samples:noneselected'] = 'No se seleccionó ninguna muestra.';
$string['samples:previewsql'] = 'Mostrar SQL';
$string['samples:requires'] = 'Requiere {$a}';
$string['samples:requiresmissing'] = 'Omitido (el plugin requerido no está instalado): {$a}.';
$string['samples:requiresmissingbadge'] = 'Requiere {$a} (no instalado)';
$string['samples:showall'] = 'Mostrar {$a} muestra(s) que necesitan un plugin no instalado aquí';
$string['samples:samplelinklabel'] = 'Cargar informe SQL de ejemplo';
$string['samples:sampleprefix'] = 'Ejemplo: {$a}';
$string['samples:selectall'] = 'Seleccionar todo';
$string['samples:selectnone'] = 'No seleccionar ninguno';
$string['samples:title'] = 'Cargar informes SQL de ejemplo';
$string['samples:titlesingle'] = 'Cargar informe SQL de ejemplo';
$string['saveandpublish'] = 'Guardar y publicar';
$string['saveandpublishedit'] = 'Publicar y continuar editando';
$string['savedandpublished'] = 'Cambios guardados e informe publicado';
$string['savedpublishfailed'] = 'Cambios guardados, pero falló la publicación: {$a}';
$string['schedule'] = 'Programar envíos por correo';
$string['selectcolumn'] = '(elegir columna)';
$string['settings:aigenerate'] = 'Generación de SQL con IA';
$string['settings:aigenerate_desc'] = 'Mostrar un cuadro de preguntas de IA en el formulario de edición de la consulta. Requiere que el plugin local_sqlchat esté instalado y configurado.';
$string['settings:denycolumns'] = 'Lista de exclusión de columnas sensibles';
$string['settings:denycolumns_desc'] = 'Lista de nombres de columna, separados por comas, espacios o saltos de línea, que se eliminarán de cualquier resultado SELECT introspeccionado.';
$string['settings:denytables'] = 'Lista de exclusión de tablas';
$string['settings:denytables_desc'] = 'Lista de nombres de tabla, separados por comas, espacios o saltos de línea, que nunca podrán consultarse. Se inicializa con la lista integrada del plugin de tablas protegidas (config, sessions, tokens, historial de contraseñas y similares). Esta lista es totalmente editable — eliminar una entrada permite consultar esa tabla, así que edítela con cuidado.';
$string['settings:enumfilterthreshold'] = 'Umbral de filtro desplegable';
$string['settings:enumfilterthreshold_desc'] = 'Cuando una columna de texto tiene este número de valores distintos o menos (medido en el momento de publicar), su filtro de informe se renderiza como un desplegable con esos valores en lugar de un cuadro de texto libre. Establezca en 0 para desactivar y mantener todas las columnas de texto como filtros de texto libre.';
$string['settings:enumrowceiling'] = 'Límite de filas para el filtro desplegable';
$string['settings:enumrowceiling_desc'] = 'Omitir la detección de filtro desplegable cuando una vista publicada tenga más filas que este número, de modo que un informe grande no pague un análisis de valores distintos por columna en el momento de publicar (todas sus columnas de texto permanecerán como texto libre). Establezca en 0 para analizar siempre, sin importar el tamaño. Solo relevante cuando el umbral de filtro desplegable es distinto de cero.';
$string['settings:enabled'] = 'Activar SQL Report';
$string['settings:enabled_desc'] = 'Cuando está desmarcado, el plugin se desactiva: su entrada se elimina del menú Informes y sus páginas (lista, edición, ejecución, gráfico) quedan bloqueadas. Los informes publicados creados a través de Report Builder no se ven afectados.';
$string['settings:sharedrepository'] = 'Repositorio de fuentes de informe compartido';
$string['settings:sharedrepository_desc'] = 'URL del repositorio de GitHub que contiene archivos de exportación de fuentes de informe compartidas. Los autores pueden explorarlo e importar sus fuentes de informe como borradores. Déjelo en blanco para desactivar el explorador de repositorio compartido.';
$string['settings:sharedrepositoryenabled'] = 'Activar el repositorio de fuentes de informe compartido';
$string['settings:sharedrepositoryenabled_desc'] = 'Permitir a los autores explorar e importar desde el repositorio de fuentes de informe compartido configurado a continuación. Desactivado de forma predeterminada; mientras esté desactivado, no se realiza ninguna solicitud al repositorio remoto y su página de exploración y sus enlaces quedan ocultos.';
$string['settings:showbraces'] = 'Mostrar llaves de tabla en el editor';
$string['settings:showbraces_desc'] = 'Mostrar las llaves {tabla} de Moodle alrededor de los nombres de tabla en el editor SQL. Cuando está desactivado, las tablas se muestran sin llaves; en cualquier caso, nunca necesita escribirlas — las llaves se añaden automáticamente al guardar.';
$string['settings:showlastmodified'] = 'Mostrar columna de última modificación';
$string['settings:showlastmodified_desc'] = 'Mostrar una columna ordenable "Última modificación" en la lista de fuentes de informe.';
$string['settings:syntaxhighlight'] = 'Resaltado de sintaxis SQL y autocompletado';
$string['settings:syntaxhighlight_desc'] = 'Activar un editor SQL CodeMirror 6 en el formulario de consulta. Sugiere palabras clave SQL además de nombres de tablas y columnas de Moodle tomados de la base de datos en vivo.';
$string['settings:viewretaindays'] = 'Retención del historial de visualizaciones (días)';
$string['settings:viewretaindays_desc'] = 'Cuántos días conservar el historial de auditoría de visualizaciones de informes. Las filas más antiguas se eliminan mediante una tarea programada. Establezca en 0 para conservar el historial indefinidamente.';
$string['sql:approve'] = 'Aprobar y publicar fuentes de informe';
$string['sql:author'] = 'Crear fuentes de informe SQL';
$string['sql:view'] = 'Ejecutar fuentes de informe publicadas';
$string['sql:viewall'] = 'Ver todas las fuentes de informe sin importar la audiencia';
$string['sql:viewown'] = 'Ejecutar fuentes de informe del propio curso';
$string['status'] = 'Estado';
$string['status_draft'] = 'Borrador';
$string['status_published'] = 'Publicado';
$string['strftimeviewdate'] = '%d/%m/%y, %H:%M';
$string['summaryreport'] = 'Informe resumen';
$string['task:purgeviews'] = 'Purgar el historial antiguo de visualizaciones de informes';
$string['testquery'] = 'Probando…';
$string['testview:fail'] = 'El usuario de la base de datos no puede crear ni eliminar vistas. Error: {$a}';
$string['testview:grantshint'] = 'Conceda al usuario de la base de datos de Moodle los permisos CREATE VIEW y DROP sobre el esquema (p. ej. en MySQL/MariaDB: GRANT CREATE VIEW, DROP ON moodle.* TO \'mdluser\'@\'host\';).';
$string['testview:linklabel'] = 'Ejecutar prueba de permisos de vista de base de datos';
$string['testview:ok'] = 'El usuario de la base de datos puede crear y eliminar vistas. Publicar consultas debería funcionar.';
$string['testview:run'] = 'Ejecutar prueba';
$string['testview:intro'] = 'Esta prueba crea y elimina inmediatamente una vista de base de datos desechable para confirmar que el usuario de la base de datos de Moodle tiene los permisos CREATE VIEW y DROP.';
$string['testview:title'] = 'Prueba de permisos de vista de base de datos';
$string['timecreated'] = 'Fecha de creación';
$string['tokenhintcase'] = 'Transformación de mayúsculas/minúsculas: upper | lower | title | sentence (solo visualización)';
$string['tokenhintcontextblock'] = 'Constante de nivel CONTEXT_BLOCK (80)';
$string['tokenhintcontextcourse'] = 'Constante de nivel CONTEXT_COURSE (50)';
$string['tokenhintcontextcoursecat'] = 'Constante de nivel CONTEXT_COURSECAT (40)';
$string['tokenhintcontextmodule'] = 'Constante de nivel CONTEXT_MODULE (70)';
$string['tokenhintcontextsystem'] = 'Constante de nivel CONTEXT_SYSTEM (10)';
$string['tokenhintcontextuser'] = 'Constante de nivel CONTEXT_USER (30)';
$string['tokenhintcoursecontext'] = "Id de la fila de contexto del curso vinculado";
$string['tokenhintcourseid'] = 'Id del curso vinculado (0 = todo el sitio)';
$string['tokenhintepoch'] = 'Literal/expresión de fecha y hora a entero de epoch Unix';
$string['tokenhintlink'] = "Enlaza la celda a una ruta relativa al sitio: LINK(expr, 'path'), donde {} en path es la posición del valor, p. ej. '/user/view.php?id={}'. Añada una columna clave, LINK(expr, keycol, 'path'), para basar el enlace en otra columna de salida.";
$string['tokenhintnow'] = 'Hora actual como entero de epoch Unix';
$string['tokenhintpagecourse'] = 'Delimita un bloque/inserción al curso en el que se encuentra: PAGECOURSE(expr) marca la columna de id de curso, p. ej. PAGECOURSE(c.id) AS courseid. Se aplica según el curso de la página (se ignora en el visor de informes independiente).';
$string['tokenhintteaches'] = 'Delimita el informe a los cursos que imparte el usuario: TEACHES(expr) marca la columna de id de curso, p. ej. TEACHES(c.id) AS courseid. Se aplica por usuario; la columna permanece visible.';
$string['tokenhinttimestamp'] = 'Columna de epoch a fecha; formato opcional, p. ej. dd/mm/yyyy';
$string['tokenhintviewer'] = 'Delimita el informe al usuario que lo visualiza: VIEWER(expr) marca la columna de id de usuario, p. ej. VIEWER(u.id) AS viewerid. Se aplica por usuario; la columna se oculta en la salida.';
$string['tokenhintwwwroot'] = 'URL del sitio (wwwroot)';
$string['tourdesc'] = 'Un breve recorrido guiado por la página de listado de fuentes de informe.';
$string['tourname'] = 'Recorrido de SQL Report';
$string['tourstep1content'] = 'Comience aquí para crear una fuente de informe. Escriba una consulta SQL <em>SELECT</em>, y luego publíquela para construir un informe de Report Builder totalmente configurable — sin necesidad de PHP.';
$string['tourstep1title'] = 'Crear una fuente de informe';
$string['tourstep2content'] = 'Todas las fuentes de informe que ha guardado se listan aquí, con su propietario y estado. Ordene o filtre cualquier columna para encontrar una rápidamente.';
$string['tourstep2title'] = 'Sus fuentes de informe';
$string['tourstep3content'] = 'El estado muestra si una fuente de informe sigue siendo un <strong>Borrador</strong> o se ha <strong>Publicado</strong> como informe activo.';
$string['tourstep3title'] = 'Borrador o publicado';
$string['tourstep4content'] = 'Edite su consulta aquí, o publique un borrador para construir su informe de Report Builder activo. Despublicar retira un informe activo.';
$string['tourstep4title'] = 'Editar y publicar';
$string['tourstep5content'] = 'Este menú contiene el resto de acciones: editar en Report Builder, ver el gráfico, programar el envío por correo, copiar el código de inserción, duplicar y eliminar.';
$string['tourstep5title'] = 'Más acciones';
$string['tsfmtdd'] = 'Día, 2 dígitos (05)';
$string['tsfmtddd'] = 'Día de la semana, corto (lun)';
$string['tsfmtdddd'] = 'Día de la semana, completo (lunes)';
$string['tsfmthelpintro'] = 'Añada un formato opcional como segundo argumento, p. ej. <code>%%TIMESTAMP(u.timecreated, dd/mm/yyyy)%%</code>. Sin él, las fechas se muestran como <code>{$a}</code>. Los separadores como / - . : y los espacios se conservan.';
$string['tsfmthelptitle'] = 'Formato de visualización de fecha';
$string['tsfmthelptokens'] = 'Tokens de formato';
$string['tsfmthh'] = 'Hora, formato 24h (17)';
$string['tsfmtmi'] = 'Minutos (20)';
$string['tsfmtmm'] = 'Mes, 2 dígitos (06)';
$string['tsfmtmmm'] = 'Nombre del mes, corto (jun)';
$string['tsfmtmmmm'] = 'Nombre del mes, completo (junio)';
$string['tsfmtmon'] = 'Nombre del mes, corto (jun)';
$string['tsfmtmonth'] = 'Nombre del mes, completo (junio)';
$string['tsfmtss'] = 'Segundos (09)';
$string['tsfmtyy'] = 'Año, 2 dígitos (26)';
$string['tsfmtyyyy'] = 'Año, 4 dígitos (2026)';

$string['unpublish'] = 'Despublicar';




$string['usage:detaillabel'] = 'Detalle de uso';
$string['usage:detailtitle'] = 'Uso del informe: {$a}';
$string['usage:firstviewed'] = 'Primera visualización';
$string['usage:intro'] = 'Con qué frecuencia se ha abierto cada fuente de informe publicada. Cada apertura de un informe se registra; ordene, filtre y exporte la lista según sea necesario. El historial anterior a la ventana de retención configurada se elimina automáticamente.';
$string['usage:lastviewed'] = 'Última visualización';
$string['usage:linklabel'] = 'Uso del informe';
$string['usage:nodata'] = 'Esta fuente de informe aún no se ha abierto.';
$string['usage:perreport'] = 'Visualizaciones por informe';
$string['usage:recent'] = 'Aperturas recientes';
$string['usage:report'] = 'Informe';
$string['usage:reportn'] = 'Informe {$a}';
$string['usage:reportsdeleted'] = 'Informes eliminados';
$string['usage:title'] = 'Uso del informe';
$string['usage:topviewers'] = 'Usuarios que más lo visualizan';
$string['usage:trend'] = 'Visualizaciones en los últimos 30 días';
$string['usage:uniqueviewers'] = 'Usuarios únicos';
$string['usage:views'] = 'Visualizaciones';
$string['usage:when'] = 'Cuándo';
$string['userdocs'] = 'Documentación de usuario';
$string['useridcolumn'] = 'Restringir al usuario que visualiza';
$string['useridcolumn_help'] = 'Opcionalmente, delimite este informe para que cada persona vea solo las filas que le pertenecen. Elija la columna de salida que contiene un id de usuario; en el momento de la vista, el informe mostrará solo las filas donde esa columna sea igual al id del usuario que ha iniciado sesión. Deje "Elegir una columna…" para mostrar todas las filas a todos los usuarios de la audiencia.';
$string['useridfilter'] = 'Filtro por usuario';
$string['viewchart'] = 'Ver gráfico';
$string['visible'] = 'Visible';
$string['visible_help'] = 'Controla si este informe publicado aparece en la página de listado de consultas. Cuando está desmarcado, los usuarios con el permiso de visualización no pueden verlo. La vista de base de datos y el informe subyacentes siguen existiendo — los administradores y autores con el permiso viewall aún pueden verlo.

Para un control de acceso más fino, use la función Audiencias de Report Builder tras publicar: abra el informe, vaya a la pestaña Audiencia, y restrinja por cohorte, rol o usuario individual.';
$string['warnlinkoffsite'] = 'La ruta de %%LINK%% \'{$a}\' no es relativa al sitio, por lo que esa columna mostrará texto plano en lugar de un enlace. Use una ruta que comience con / (por ejemplo /user/view.php?id={}) — no se permiten enlaces a otros sitios.';
$string['warnlinkunnamed'] = 'El token %%LINK%% en \'{$a}\' no tiene nombre de columna de salida, por lo que esa columna mostrará texto plano en lugar de un enlace. Déle un alias, por ejemplo %%LINK(...)%% AS profile.';
$string['warnmysqldatefn'] = 'La función exclusiva de MySQL {$a} puede no funcionar en PostgreSQL. Use un equivalente multiplataforma.';
