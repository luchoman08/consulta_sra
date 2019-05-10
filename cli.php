
<?php

require_once(__DIR__ .'/lib.php');
const CSV_NAME_DEFAULT = 'students.csv';
const CSV_ROUTE_PARAM = 'csv-route';
const CSV_COOKIE_PARAM = 'cookie';
use function sra_lib\{read_student_info_csv, search_student_info, write_student_information_to_csv};
// Cookie example = 'PHPSESSID=8f1613b43a6f279e3040a0df14fd5350';
if (isset($argv[1]) && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
	print_help_info(CSV_ROUTE_PARAM, CSV_COOKIE_PARAM, CSV_NAME_DEFAULT);
}
function print_help_info($param_csv_route, $param_cookie_name, $csv_name_default) {
     	echo <<<HELP
Argumentos disponibles:
--$param_csv_route <<ruta>> (opcional, por defecto '$csv_name_default'): ruta de el archivo que contiene la información de los estudiantes
--$param_cookie_name "<<cookie>>": valor de la cookie para acceder a SIRA (recuerde pasarla con dobles comillas"
--help: pinta el menu de ayuda, renombramientos: -help, -h, -?	 
HELP;
die;
}

$longopts  = array(
    "csv-route:",     // Valor obligatorio
    "cookie:"
);
$options = getopt(null, $longopts);
$csv_name = isset($options['csv-route'])? $options['csv-route']: CSV_NAME_DEFAULT;
$cookie_value = isset($options['cookie'])? $options['cookie']: die("Debe ingresar una cookie por el parametro --cookie \n");

$student_names_arr = read_student_info_csv($csv_name);
print_r($student_names_arr);
$list_student = array();
foreach($student_names_arr as $info_student)
{
	$info_student_active = search_student_info($cookie_value, $info_student);
	array_push($list_student, $info_student_active);
}

echo "Trying to write student information <br />";
write_student_information_to_csv($list_student);
?> 
