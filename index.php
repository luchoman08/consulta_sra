<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ .'/lib.php');
require_once(__DIR__ .'/mocks/html_historial_academico.php');
const CSV_NAME_DEFAULT = 'students.csv';
const CSV_ROUTE_PARAM = 'csv-route';
const CSV_COOKIE_PARAM = 'cookie';

$CSV_COOKIE_PARAM = 'cookie';

use function sra_lib\{extract_academic_history,
    get_academic_history,
    read_student_info_csv,
    _extract_academic_history,
    generar_consulta_historial_academico_2019,
    get_student_search_results,
    write_student_information_to_csv};
use sra_lib\ConsultaHistorialAcademicoInput;
use sra_lib\StudentInputCSV;
use sra_lib\StudentSearchResult;

// Cookie example = 'PHPSESSID=8f1613b43a6f279e3040a0df14fd5350';
if (isset($_GET[1]) && in_array($_GET[1], array('--help', '-help', '-h', '-?'))) {
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
$options = $_GET;
$csv_name = isset($options[CSV_ROUTE_PARAM])? $options[CSV_ROUTE_PARAM]: CSV_NAME_DEFAULT;
$cookie_value = isset($options[CSV_COOKIE_PARAM])? $options[CSV_COOKIE_PARAM]: die("Debe ingresar una cookie por el parametro $CSV_COOKIE_PARAM \n");

//$student_names_arr = read_student_info_csv($csv_name);
$list_student_csv = read_student_info_csv('students.csv', true);
$list_student_info = array();

foreach($list_student_csv as $key => $info_student)
{
    $info_student_active = array_shift(get_student_search_results($info_student, $cookie_value));
    array_push($list_student_info, $info_student_active);
}
/** @var  $info_student StudentSearchResult */
$academic_histories = array();


$error_num_docs = array();
foreach($list_student_info as $key => $info_student) {
    try {
        $input_history_academic_query = generar_consulta_historial_academico_2019(
            $info_student->codigo_estudiante,
            $info_student->codigo_persona,
            $info_student->programa,
            $info_student->sede,
            $info_student->jornada
        );
        $academic_history = get_academic_history($info_student, $input_history_academic_query, $cookie_value);
        echo '<div class="academic_history">';
        echo json_encode($academic_history);
        echo '</div>';
        array_push($academic_histories, $academic_history);
    } catch(Exception $e) {
        array_push($error_num_docs, $info_student->documento);
    }
}
echo '<br/>';
echo '<div class="academic_histories">';
print_r(json_encode($academic_histories));
echo '</div>';
echo '<br />';


print_r($error_num_docs);