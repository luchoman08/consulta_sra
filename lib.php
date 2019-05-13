<?php
namespace sra_lib;
require_once (__DIR__ . '/vendor/autoload.php');

use function array_pop;
use function array_push;
use function array_shift;
use function gettype;
use function http_build_query;
use function in_array;
use PHPSelector\Dom;
use function curl_init;
use function curl_setopt;
use function file_exists;
use function json_encode;
use function print_help_info;
use function str_replace;
use function strpos;
use function var_dump;
const CSV_INDEX_DOC_NUMBER = 0;
const CSV_INDEX_FIRST_NAME = 1;
const CSV_INDEX_LAST_NAME = 2;
const CSV_INDEX_PROGRAM = 3;


function _print_dom_node_list(\DOMNodeList $list) {
    $temp_dom = new \DOMDocument();
    foreach($list as $n) $temp_dom->appendChild($temp_dom->importNode($n,true));
    print_r($temp_dom->saveHTML());
}
function _append_childs(\DOMDocument &$dom, \DOMNodeList $list, $deep=true) {
    for($i=0; $i<$list->length; $i++) {
        $dom->appendChild($dom->importNode($list->item($i), $deep));
    }
}





class RegistroHistorialAcademico {

    /**
     * @var bool
     */
    public $cancelo_semestre;

    /**
     * Ejemplo: PERIODO: AGOSTO/2010 - DICIEMBRE/2010
     * @var string
     */
    public $nombre_periodo;

    /**
     * @var string
     */
    public $numero_documento;
    /**
     * @var string
     */
    public $codigo_programa_univalle;
    /**
     * @var $promedio string
     */
    public $promedio;
    /**
     * @var array Array de RegistroNota
     */
    public $notas;
    /**
     * @var bool
     */
    public $bajo_academico;
    /**
     * @var bool
     */
    public $estimulo_academico;
    /**
     * @var integer
     */
    public $puesto_estimulo;


}
class RegistrosHistorialAcademico {
    /**
     * @var string
     */
    public $promedio_actual_acumulado;
    /**
     * @var array Array de RegistroHistorialAcademico
     */
    public $registros;
}
class RegistroNota {
    /**
     * @var string
     * Ejemplo: 910001M
     */
    public $codigo_materia;
    /**
     * @var number
     */
    public $creditos;
    /**
     * @var string
     * Ejemplos: ['2018/02/01', '2018-12-1']
     */
    public $fecha_cancelacion_materia;

    /**
     * @var string
     * Ejemplos: ['2018/02/01', '2018-12-1']
     */
    public $fecha_reactivacion_materia;
    /**
     * @var string
     */
    public $nombre_materia;

    public $grupo;
    /**
     * @var string
     * Ejemplos: ['4.5', '1,2', '5']
     */
    public $nota;
}
function _print_node_element(\DOMElement $el) {
    $doc = new \DOMDocument();
    $doc->appendChild($doc->importNode($el, true));
    print_r($doc->saveHTML());
}
function _node_list_map(\DOMNodeList $list, callable $f): array {
    $results = array();
    for($i=0; $i<$list->length; $i++) {
        $result = $f($list->item($i));
        array_push($results, $result);
    }
    return $results;
}

function extract_academic_resolution($student_folder_html) {
    $doc = new \DOMDocument();
    $doc->loadHTML($student_folder_html);
    $finder = new \DomXPath($doc);
    $name = 'hia_rep_codigo';
    $possible_resolucion_input = $finder->query("//*[contains(concat(' ', normalize-space(@name), ' '), ' $name')]");
    $resolution_val = $possible_resolucion_input->item(0)->getAttribute('value');
    return $resolution_val;
}


function get_student_resolution($cookie_value, $student_code, $per_codigo, $name, $lastname, $program_code, $sed_code, $jornada) {
    $html = _get_student_folder_html($cookie_value, $student_code, $per_codigo, $name, $lastname, $program_code, $sed_code, $jornada);
    return extract_academic_resolution($html);
}


function _get_student_folder_html($cookie_value, $student_code, $per_codigo, $name, $lastname, $program_code, $sed_code, $jornada) {
    $_name = str_replace(" ", '+', $name);
    $_lastname = str_replace(' ', '+', $lastname);
    $_name  = str_replace("Ñ","%D1",$_name);
    $_lastname = str_replace("Ñ","%D1",$_lastname);
    $encoded_params = "deu_est_per_codigo=$per_codigo&codigo_estudiante=$student_code&wincombomep_codigo_estudiante=$_name++$_lastname+-%3E+$program_code-$sed_code-$jornada&modulo=Academica&accion=Consultar+Estudiante";
    $curl = curl_init('https://swebse32.univalle.edu.co/sra//paquetes/academica/index.php');
    curl_setopt($curl, CURLOPT_ENCODING ,"UTF-8");
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded_params);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Cookie: $cookie_value",
        "User-Agent"=>"Mozilla/5.0 (X11; Linux x86_64; rv:67.0) Gecko/20100101 Firefox/67.0",
        "Accept"=>"text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Content-Type"=> "application/x-www-form-urlencoded",
        "Conection"=>"keep-alive",
        "Upgrade-Insecure-Requests"=>"1"
    ));
    $resp = utf8_encode(curl_exec($curl));
    curl_close($curl);
    return $resp;
}

/**
 * @param $student_search_result StudentSearchResult
 * @param $student_info ConsultaHistorialAcademicoInput
 * @param $cookie_value
 * @return RegistrosHistorialAcademico
 *
 */
function get_academic_history($student_search_result, $student_info, $cookie_value): RegistrosHistorialAcademico {
    $student_info->hia_rep_codigo = get_student_resolution(
        $cookie_value,
        $student_info->hia_est_codigo,
        $student_info->hia_per_codigo,
        $student_search_result->nombre,
        $student_search_result->apellidos,
        $student_info->hia_pra_codigo,
        $student_search_result->sede,
        $student_info->hia_jor_codigo);
    $html = _get_academic_history_html($student_info, $cookie_value);

    $history_registry = _extract_academic_history($student_search_result->documento, $student_info->hia_pra_codigo, $html);
    return $history_registry;
}
/**
 * @param $student_info StudentInputCSV
 * @param string $academic_html
 * @return array
 */
function _extract_academic_history($doc_number, $program_code, string $academic_html): RegistrosHistorialAcademico {

        $registros_historial_academico = new RegistrosHistorialAcademico();

        $get_semester_average = function (\DOMElement $period_table): string {
            $period_trs = $period_table->getElementsByTagName('tr');
            $last_tr = $period_trs->item($period_trs->length-1);
            $tds = $last_tr->getElementsByTagName('td');
            $last_td = $tds->item($tds->length-1);
            $average = $last_td->nodeValue;
            return $average;
        };
        
        $get_acumulated_average= function (\DOMDocument $academic_report_page) {
            $finder = new \DomXPath($academic_report_page);
            $classname = 'error';
            /**
             * <td class="error" align="right">
             *  Promedio Total Acumulado del estudiante: <font size="3"><b>3.78</b></font>
             * </td>
             */
            $possible_average_elements =  $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
            for($i=0; $i<$possible_average_elements->length; $i++) {
                $possible_element = $possible_average_elements->item($i);
                $node_value=$possible_element->nodeValue;
                print_r($node_value);
                if(strpos($node_value, 'Promedio Total Acumulado') !== false) {
                    return $possible_element
                        ->getElementsByTagName('font')
                        ->item(0)
                        ->nodeValue;
                }
            }
            return null;
        };
        
        $tr_final_grade_to_nota = function (\DOMElement $tr_grade): RegistroNota {
            $nota = new RegistroNota();
            echo '<pre>';
            $codigo_index = 0;
            $grupo_index = 2;
            $nombre_index = 4;
            $nota_index = 1;
            $creditos_index = 8;
            $fecha_cancelacion_index = 9;
            $fecha_reactivacion_index = 10;
            $nota->codigo_materia = $tr_grade->childNodes->item($codigo_index)->nodeValue;
            $nota->nombre_materia = $tr_grade->childNodes->item($nombre_index)->nodeValue;
            $nota->grupo = $tr_grade->childNodes->item($grupo_index)->nodeValue;
            $nota->nota = $tr_grade->childNodes->item($nota_index)->nodeValue;
            $nota->creditos = $tr_grade->childNodes->item($creditos_index)->nodeValue;
            $nota->fecha_cancelacion_materia = $tr_grade->childNodes->item($fecha_cancelacion_index)->nodeValue;
            $nota->fecha_reactivacion_materia = $tr_grade->childNodes->item($fecha_reactivacion_index)->nodeValue;
            return $nota;
        };

        $get_subject_trs = function (\DOMElement $period_table): \DOMDocument {
            $subject_trs = new \DOMDocument();
            $tmp_dom = new \DOMDocument();
            $tmp_dom->appendChild($tmp_dom->importNode($period_table));

            $td_classes_related_to_grade_class = ['normalNegro','normalRojo'];
            $period_table_trs = $period_table->getElementsByTagName('tr');

            for($i=0; $i<$period_table_trs->length; $i++) {
                $first_td = $period_table_trs->item($i)->childNodes->item(0);
                if($first_td instanceof \DOMComment) {
                    break;
                }
                $first_td_class = $first_td->getAttribute('class');
                if(in_array($first_td_class, $td_classes_related_to_grade_class)) {
                    $subject_trs->appendChild($subject_trs->importNode($first_td->parentNode, true));
                }
            }
            return $subject_trs;
        };
        $is_low_performance_form = function (\DOMElement $form): bool {
          $inputs = $form->getElementsByTagName('input');
          for($i = 0; $i<$inputs->length; $i++) {
              $input_title = $inputs->item($i)->getAttribute('title');
              if( strpos($input_title, 'Bajos Rendimientos') !== false ) {
                  return true;
              }
          }
          return false;
        };
        $is_stimulus_form = function (\DOMElement $form): bool {
            $inputs = $form->getElementsByTagName('input');
            for($i = 0; $i<$inputs->length; $i++) {
                $input_title = $inputs->item($i)->getAttribute('title');
                if( strpos($input_title, 'Detalle Est') !== false ) {
                    return true;
                }
            }
            return false;
        };
        $get_period_name = function(\DOMElement $period_table) {
            $period_table_td = $period_table->getElementsByTagName('td')->item(0);
            return  $period_table_td->getElementsByTagName('font')->item(0)->nodeValue;
        };
        $get_low_academic_performance = function (\DOMElement $period_table) use ($is_low_performance_form) {
            $table_forms = $period_table->getElementsByTagName('form');
            if($table_forms->length >= 1) {
                $forms_are_low_performance = _node_list_map($table_forms, $is_low_performance_form);
                return in_array(true, $forms_are_low_performance);
            }
            return false;
        };
        $get_stimulus = function (\DOMElement $period_table) use ($is_stimulus_form) {
            $table_forms = $period_table->getElementsByTagName('form');
            if($table_forms->length >= 1) {
                $forms_are_low_performance = _node_list_map($table_forms, $is_stimulus_form);
                return in_array(true, $forms_are_low_performance);
            }
            return false;
        };
        $get_period_grades = function (\DOMElement $table) use( $get_subject_trs, $tr_final_grade_to_nota): array  {
            $subject_trs =  $get_subject_trs($table);
            $grades = _node_list_map($subject_trs->childNodes, $tr_final_grade_to_nota);
            return $grades;
        };
        $period_table_to_registro_historial_academico = function (\DOMDocument $doc ,\DOMElement $table)
        use (
            $get_semester_average,
            $get_low_academic_performance,
            $get_period_name,
            $get_period_grades,
            $get_stimulus,
            $doc_number,
            $program_code) {
            $registro_historial = new RegistroHistorialAcademico();
            $registro_historial->nombre_periodo = $get_period_name($table);
            $registro_historial->promedio = $get_semester_average($table);
            $registro_historial->numero_documento = $doc_number;
            $registro_historial->codigo_programa_univalle = $program_code;
            $registro_historial->notas = $get_period_grades($table);
            $registro_historial->estimulo_academico = $get_stimulus($table);
            $registro_historial->bajo_academico = $get_low_academic_performance($table);
            return $registro_historial;
        };

        $get_period_tables = function (\DOMDocument $dom): \DOMDocument {
            $finder = new \DomXPath($dom);
            $classname = 'normalAzulB';
            /**
             * <td colspan="7" width="100%" class="normalAzulB" bgcolor="#CCCCCC">
             * <font size="2">PERIODO: AGOSTO/2014 - DICIEMBRE/2014 </font>
             * </td>
             */
            $posible_period_name_td =  $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
            $period_tables = new \DOMDocument();
            for($i=0; $i<$posible_period_name_td->length; $i++) {
                /** Can exist other elements than are no period name and have normalAzulB class
                 *, but all elements with class normalAzulB and colspan = 7 are period names
                 */
                if($posible_period_name_td->item($i)->getAttribute('colspan') == '7') {
                    $period_tables->appendChild($period_tables->importNode($posible_period_name_td->item($i)->parentNode->parentNode->parentNode, true));
                }
            }
            return $period_tables;
        };
        $dom = new \DomDocument;

        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$academic_html);
        $period_tables = $get_period_tables($dom);
        $lista_registros_historial_academico = _node_list_map($period_tables->childNodes,
            function($table) use ($dom, $period_table_to_registro_historial_academico) {
                return $period_table_to_registro_historial_academico($dom, $table);
            });
        $registros_historial_academico->registros = $lista_registros_historial_academico;
        $registros_historial_academico->promedio_actual_acumulado =  $get_acumulated_average($dom);
        //_print_dom_node_list($period_tables->childNodes);
        //$subject_trs = $get_subject_trs($period_tables->childNodes->item(0))->childNodes;
        //print_r($tr_final_grade_to_nota($subject_trs->item(0)));die;
        return $registros_historial_academico;
}



function generar_consulta_historial_academico_2019($codigo, $per_codigo, $programa_codigo, $sed_codigo, $jornada) {
    return new ConsultaHistorialAcademicoInput(
        $codigo,
        $per_codigo,
        $programa_codigo,
        $sed_codigo,
        476, // Resolución 2019,
        'COMPLETA',
        $jornada
    );
}


/**
 * Class StudentSearchResult
 * @property $codigo_persona string
 * @property $codigo_estudiante string
 * @property $documento string
 * @property $sede string Ejemplo 00
 * @property $nombre string
 * @property $apellidos string
 * @property $tipo_documento
 * @property $programa string
 * @property $jornada string Ejemplo: DIU
 */
class StudentSearchResult{}

/**
 * Class StudentInputCSV
 * @property $doc_number
 * @property $name
 * @property $last_name
 * @property $program_code
 */
class StudentInputCSV{}


/**
 * Todods los estudiantes con seguimiento de el semestre pasado, con profesional asignado
 *
 * Salida: quienes están matriculados en el periodo 2019-1
 */
/**
 * Class ConsultaHistorialAcademicoInput
 * @package sra_lib
 * Información requerida para acceder a la pagina donde se listan los periodos con materias perdias, ganadas
 * y canceladas
 */
class ConsultaHistorialAcademicoInput {
    public $accion;
    public $DetalleCarpeta;
    /**
     * Codigo de estudiante con prefijo, ej: 201327958
     * @var $hia_est_codigo integer
     */
    public $hia_est_codigo;
    /**
     * Codigo de jornada, valores posibles: DIU, NOC, VES
     * @var string $hia_jor_codigo
     */
    public $hia_jor_codigo;

    /**
     * Codigo de programa, ej: 3745
     * @var integer
     */
    public $hia_pra_codigo;
    /**
     * Codigo de persona, tabla de el SRA
     * @var number
     */
    public $hia_per_codigo;

    /**
     * @var integer
     */
    public $hia_rep_codigo;
    /**
     * Codigo de la sede, ej: '00' (para Cali)
     * @var string
     */
    public $hia_sed_codigo;
    /**
     * Modulo de consulta, valores conocidos: 'Academica'
     * @var string
     */
    public $modulo;
    /**
     * Tipo de carpeta a generar
     * Valores posibles: 'BASICA', 'COMPLETA', la básica no muestra las materias canceladas
     * @var string
     */
    public $TipoCarpeta;

    public $Ventana;
    public $versionImprimible;
    public function __construct($hia_est_codigo, $hia_per_codigo, $hia_pra_codigo, $hia_sed_codigo, $hia_rep_codigo, $tipo_carpeta = 'COMPLETA', $hia_jor_codigo='DIU')
    {
        $this->accion = 'mostrarDetalleUnaCarpeta';
        $this->hia_est_codigo = $hia_est_codigo;
        $this->hia_pra_codigo = $hia_pra_codigo;
        $this->hia_per_codigo = $hia_per_codigo;
        $this->hia_rep_codigo = $hia_rep_codigo;
        $this->hia_sed_codigo = $hia_sed_codigo;
        $this->modulo = 'Academica';
        $this->Ventana = '';
        $this->versionImprimible= '';
        $this->DetalleCarpeta = 'COMPLETA';
        $this->TipoCarpeta = $tipo_carpeta;
        $this->hia_jor_codigo = $hia_jor_codigo;
    }
    public function get_url_query() {
        return http_build_query($this).'&accion=Generar+Carpeta';
    }

}




function _get_academic_history_html(ConsultaHistorialAcademicoInput $input, $cookie_value) {



    header('Content-Type: text/html; charset=utf-8');
    $curl = curl_init('https://swebse32.univalle.edu.co/sra//paquetes/academica/index.php');
    curl_setopt($curl, CURLOPT_ENCODING ,"UTF-8");
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    $post_intput = $input->get_url_query();
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_intput);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Cookie: $cookie_value",
        "User-Agent"=>"Mozilla/5.0 (X11; Linux x86_64; rv:67.0) Gecko/20100101 Firefox/67.0",
        "Accept"=>"text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Content-Type"=> "application/x-www-form-urlencoded",
        "Conection"=>"keep-alive",
        "Upgrade-Insecure-Requests"=>"1"
    ));
    $resp = utf8_encode(curl_exec($curl));

    curl_close($curl);

    return $resp;
}
function get_student_information($cod_est, $cookie_value){
    $curl = curl_init();

    $str_gif = str_replace("GIF","GI%46",$cod_est);
    $str_enhe = str_replace("Ñ","%D1",$str_gif);
    $url_patron = $str_enhe;
    echo '<br />'.$url_patron;
    curl_setopt($curl, CURLOPT_URL, "https://swebse32.univalle.edu.co/sra/paquetes/herramientas/wincombo.php?opcion=estudianteConsulta&patron=$url_patron&variableCalculada=0");

    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Cookie: $cookie_value"
    ));

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec($curl);

    curl_close($curl);

    return $resp;

}


function read_student_info_csv($csv_route='students.csv', $skip_first_row=false){
    $row = 1;
    $students_info = array();
    if(!file_exists($csv_route)) {
        throw new \ErrorException("No existe el archivo <<$csv_route>>");
    }
    if (($handle = fopen($csv_route, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            /** @var $student_info StudentInputCSV */
            $student_info = new \stdClass();
            $student_info->doc_number = $data[CSV_INDEX_DOC_NUMBER];
            $student_info->name = $data[CSV_INDEX_FIRST_NAME];
            $student_info->last_name = $data[CSV_INDEX_LAST_NAME];
            $student_info->program_code = $data[CSV_INDEX_PROGRAM];
            array_push($students_info, $student_info);

        }
        fclose($handle);
    } else {
        echo "Error al cargar el archivo '$csv_route''";
        throw new \ErrorException("Error de lectura de archivo de entrada");
    }
    if($skip_first_row) {
        array_shift($students_info);
    }
    return $students_info;
}

/**
 * @param $html string Html representando el menu emergente de la busqueda de un usuario
 * @return \DOMElement  Nodos que representa la tabla en donde se alojan los resultados
 * @link https://swebse32.univalle.edu.co/sra/paquetes/herramientas/wincombo.php?opcion=estudianteConsulta&patron=1088976885&variableCalculada=0
 */
function _get_students_results_table($html) {
    $dom = new \DomDocument;

    $dom->loadHTML($html);

    $dom->preserveWhiteSpace = false;
    // El documento tiene muchas tablas :v
    $tables = $dom->getElementsByTagName('table');
    /**
     * Increiblemente la tabla con la información de el estudiante tiene una tabla para el titulo,
     * otra tabla para la información principal y una tercera tabla para el total de registros .-.
     *
     * En ese sentido, la segunda tabla es la que guarda la información que se nesecita
     * Ejemplo
     * <table>
     * 	 	<table alt="Esta es la tabla para el titulo, si, titulo es una tabla">
     * 		</table>
     * 		<table alt="Esta es la tabla con la información de los resultados">
     *		</table>
     *  	<table alt="Esta es la tabla para el conteo de resultados, si, el conteo de resultados es una tabla">
     * 		</table>
     * </table>
     */
    $tables_principal_info = $tables->item(0)->getElementsByTagName('table');
    return $tables_principal_info->item(1);
}

function _get_rows_student_information(\DOMElement $table): array {
    $rows = $table->getElementsByTagName('tr');
    $correct_rows = array();
    for($i = 1 /* saltar el row con headers */; $i < count($rows); $i++){
        $correct_rows[] = $rows->item($i);
    }
    return $correct_rows;
}

/**
 * Extract and return students from search result after
 * search query is sended to server
 *
 * If $filter_program is given, only returns the students
 * that belongs to the program with code $filter_program
 * @param string $html result HTML after searching for a student
 * @return array Array of StudentSearchResult
 */
function get_student_search_result_from_html(string $html, $filter_program = null) {
    $table_info_results = _get_students_results_table($html);
    $students_result = get_students_result($table_info_results);
    if($filter_program) {
        return array_filter(
            $students_result,
            function($student) use ($filter_program) {
                /** @var $student StudentSearchResult */
                return $student->programa == $filter_program;
            });
    } else {
        return $students_result;
    }
}



function deduce_nombre_index(DOMElement $row /**/) {

    $tds =  $row->getElementsByTagName('td');
    for($i = 0 ; $i < count($tds) ; $i++) {
        if($tds->item($i)->nodeValue) {

        }
    }
}

/**
 * Recibe un array de rows [DOMElement] con la información resultante de la busqueda de un usuario
 * y retorna los objetos respectivos que contienen las propiedades de los estudiantes resultantes
 * @param array $rows
 * @return array Array of students @see StudentSearchResult
 */
function rows_student_information_to_objects(array $rows): array {
    $codigo_persona_row_number = 1;
    $codigo_estudiante_row_number = 2;
    $documento_row_number = 3;
    $nombre_row_number = 4;
    $apellidos_row_number = 5;
    $programa_row_number = 6;

    $students = array();
    /** @var  $row DOMElement */
    foreach($rows as $row) {
        /** @var $student StudentSearchResult */
        $student = new \stdClass();
        $student->nombre = $row->getElementsByTagName('td')->item($nombre_row_number)->nodeValue;
        $student->apellidos = $row->getElementsByTagName('td')->item($apellidos_row_number)->nodeValue;
        $student->codigo_persona = $row->getElementsByTagName('td')->item($codigo_persona_row_number)->nodeValue;
        $student->codigo_estudiante = $row->getElementsByTagName('td')->item($codigo_estudiante_row_number)->nodeValue;
        $row_programa_value = $row->getElementsByTagName('td')->item($programa_row_number)->nodeValue;
        $program_parts = explode("-", $row_programa_value);
        $student->programa = $program_parts[0];
        $student->sede = $program_parts[1];
        $student->jornada = $program_parts[2];

        $row_docuement_value = $row->getElementsByTagName('td')->item($documento_row_number)->nodeValue;
        $partes_documento = explode(" ", (string)$row_docuement_value);
        $student->tipo_documento= $partes_documento[0];
        $student->documento = $partes_documento[1];
        $students[] = $student;
    }
    return $students;

}

/**
 * @param $table \DOMElement
 * @return array
 */
function get_students_result($table): array {
    $rows_student_information = _get_rows_student_information($table);
    $students = rows_student_information_to_objects($rows_student_information);
    return $students;
}

/**
 * @param $info_student StudentInputCSV
 * @return array Array of StudentSearchResult
 */
function get_student_search_results($info_student, $cookie_value): array {
    $st_apellidos = str_replace(" ","+",$info_student->last_name);
    $st_nombres = str_replace(" ","+",$info_student->name);
    $st_apellidos = str_replace("Ñ","%D1",$st_apellidos);
    $st_nombres = str_replace("Ñ","%D1",$st_nombres);
    $param = $st_apellidos.'-'.$st_nombres;
    $html = get_student_information($param, $cookie_value);
    $results =  get_student_search_result_from_html($html, $info_student->program_code);
    return $results;
}

/**
 * @param $info_student StudentInputCSV
 * @return array|string
 */
function search_student_info($cookie_value, $info_student){
    $st_apellidos_sp = $info_student->last_name;
    $st_apellidos = str_replace(" ","+",$st_apellidos_sp);
    $st_nombres_sp = $info_student->name;
    $st_nombres = str_replace(" ","+",$st_nombres_sp);
    $st_documento = $info_student->doc_number;
    /** Tabla con los resultados de la busqueda */
    $student_results = get_students_search_results($info_student->program_code, $cookie_value);
    /** @var $student_result StudentSearchResult */
    foreach($student_results as $student_result){
        $cod_per = $student_result->codigo_persona;
        $cod_estu = $student_result->codigo_estudiante;
        $tipo_doc = $student_result->tipo_documento;
        $numero_doc = $student_result->documento;
        $nombre = $student_result->nombre;
        $apellido = $student_result->apellidos;
        $plan = $student_result->programa;
        $sede = $student_result->sede;
        $mod = $student_result->jornada;

        $ocurrencia_igual_nombre_diferente_documento="NO";

        $nombre_wsp=str_replace("%20"," ",$st_nombres);
        $apellido_wsp=str_replace("%20"," ",$st_apellidos);

        $apellido_wsp_reg=str_replace("+"," ", $apellido_wsp);

        $info_student_active = [];

        if($nombre === $nombre_wsp && $apellido_wsp_reg === $apellido && $numero_doc !== $st_documento){

            $ocurrencia_igual_nombre_diferente_documento="SI";


            $info_student = search_student_info_each_program($cookie_value,
                $cod_per, $cod_estu, $tipo_doc, $numero_doc, $nombre, $apellido, $plan, $sede, $mod, $ocurrencia_igual_nombre_diferente_documento);

            $info_student_sp = explode(",", $info_student);

            $info_student_active = $info_student;

            if($info_student_sp[8] == "ACTIVO" || $info_student_sp[8] == "INACTIVO"){
                return $info_student_active;
            }
        }

        if($numero_doc === $st_documento){

            $info_student = search_student_info_each_program($cookie_value,
                $cod_per,$cod_estu,$tipo_doc,$numero_doc,$nombre,$apellido,$plan,$sede,$mod,$ocurrencia_igual_nombre_diferente_documento);


            $info_student_sp = explode(",", $info_student);

            $info_student_active = $info_student;
            if($info_student_sp[8]=="ACTIVO" || $info_student_sp[8] == "INACTIVO"){
                return $info_student_active;
            }

        }

        if($info_student_active === []){

            return ",,$st_documento,$st_nombres,$st_apellidos_sp,NO ENCONTRADO";
        }
    }

    return $info_student_active;
}


function search_student_info_each_program( $cookie_value, $cod_per,$cod_estu,$tipo_doc,$numero_doc,$nombre,$apellido,$plan,$sede,$mod,$ocurrencia_igual_nombre_diferente_documento){

    $chp2 = curl_init();

    curl_setopt($chp2, CURLOPT_URL, "https://swebse32.univalle.edu.co/sra//paquetes/academica/index.php");

    curl_setopt($chp2, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($chp2, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($chp2, CURLOPT_HTTPHEADER, array(
        "Cookie: $cookie_value",
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'DNT: 1'
    ));

    curl_setopt($chp2, CURLOPT_POST, 1);

    $fields = "deu_est_per_codigo=$cod_per&codigo_estudiante=$cod_estu&wincombomep_codigo_estudiante=$nombre++$apellido->+$plan-$sede-$mod&modulo=Academica&accion=Consultar+Estudiante";

    curl_setopt($chp2, CURLOPT_POSTFIELDS, $fields);


    $respo2 = curl_exec($chp2);

    curl_close($chp2);


    $dom2 = new \DOMDocument();

    $dom2->loadHTML($respo2);

    $dom2->preserveWhiteSpace = false;

    $tables = $dom2->getElementsByTagName('table');

    $rows = $tables->item(0)->getElementsByTagName('tr');

    $cols = $dom2->getElementsByTagName('td');
    $estado = 'INACTIVO';
    $has_cancelled = "NO";

    for ($i = 0; $i <= count($cols); $i++) {
        $periodo_academico = (string)$cols->item($i)->nodeValue;
        #echo $periodo_academico.'<br />';
        if(substr_count($periodo_academico,"FEBRERO/2018 - JUNIO/2018") == 1){
            //echo 'I '.$i.' '.$periodo_academico;
            $estado = 'ACTIVO';
            if((string)$cols->item($i)->getAttribute('class')=="normalRojo"){
                $has_cancelled = "SI";
                echo $has_cancelled.' ha cancelado <br />';
            }

        }

    }
    $nombre_sp = str_replace("+"," ",$nombre);
    $apellido_sp = str_replace("+"," ",$apellido);

    $information_student = "$cod_estu,$tipo_doc,$numero_doc,$nombre_sp,$apellido_sp,$plan,$sede,$mod,$estado,$ocurrencia_igual_nombre_diferente_documento,$has_cancelled";

    return $information_student;
}

function write_student_information_to_csv($student_info_arr){
    echo "Entered to write csv <br />";

    $file = fopen("estudiantes.csv","w");
    echo "Opened estudiantes file successfully <br />";

    fputcsv($file,explode(',',"CODIGO,TIPO DOCUMENTO,DOCUMENTO,NOMBRES,APELLIDOS,PLAN,SEDE,MODALIDAD,ESTADO,IGUAL NOMBRE-DIFERENTE DOCUMENTO,HA CANCELADO"));

    //echo "Starting to write information on file <br />";
    foreach ($student_info_arr as $line)
    {
        //echo "Entered to iterate information <br />";
        fputcsv($file,explode(',',$line));
        echo $line.'<br />';
    }
    //echo "Finished writing information <br />";

    //echo "Trying to close file <br />";
    fclose($file);
    //echo "Closed file successfully";

    //echo '<br>Fin '.$estado;
}