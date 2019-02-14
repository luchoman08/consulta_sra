
<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
$cookie_value = 'PHPSESSID=8f1613b43a6f279e3040a0df14fd5350';
const CSV_INDEX_DOC_NUMBER = 0;
const CSV_INDEX_FIRST_NAME = 1;
const CSV_INDEX_LAST_NAME = 2;
const CSV_INDEX_PROGRAM = 3;
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


function get_student_information($cod_est){
	global $cookie_value;
   $curl = curl_init();
   
   $str_gif = str_replace("GIF","GI%46",$cod_est);
   $str_enhe = str_replace("Ñ","%D1",$str_gif);
   $url_patron = $str_enhe;
   
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
function read_student_info_csv(){
	$students_info = array();
	$row = 1;
	if (($handle = fopen("students.csv", "r")) !== FALSE) {
  		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    		$row++;
    		/** @var $student_info StudentInputCSV */
            $student_info = new stdClass();
    		$student_info->doc_number = $data[CSV_INDEX_DOC_NUMBER];
            $student_info->name = $data[CSV_INDEX_FIRST_NAME];
            $student_info->last_name = $data[CSV_INDEX_LAST_NAME];
            $student_info->program_code = $data[CSV_INDEX_PROGRAM];
            array_push($students_info, $student_info);

  		}
  		fclose($handle);
	}
	return $students_info;
}
/**
 * @param $html string Html representando el menu emergente de la busqueda de un usuario
 * @return DOMElement  Nodos que representa la tabla en donde se alojan los resultados
 * @link https://swebse32.univalle.edu.co/sra/paquetes/herramientas/wincombo.php?opcion=estudianteConsulta&patron=1088976885&variableCalculada=0
 */
function _get_students_results_table($html) {
	$dom = new domDocument; 
   
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

function _get_rows_student_information(DOMElement $table): array {
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
function get_students_search_result(string $html, $filter_program = null) {
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
		$student = new stdClass();
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
 * @param $table DOMElement
 * @return array
 */
function get_students_result($table): array {
	$rows_student_information = _get_rows_student_information($table);
	$students = rows_student_information_to_objects($rows_student_information);
	return $students;
}

/**
 * @param $info_student StudentInputCSV
 * @return array|string
 */
function search_student_info($info_student){
	$st_apellidos_sp = $info_student->last_name;
	$st_apellidos = str_replace(" ","+",$st_apellidos_sp);
	$st_nombres_sp = $info_student->name;
	$st_nombres = str_replace(" ","+",$st_nombres_sp);
	$st_documento = $info_student->doc_number;
	
	$param = $st_apellidos.'-'.$st_nombres;
	echo $param;
	$html = get_student_information($param);
	
	echo $html;
	 	
	 /** Tabla con los resultados de la busqueda */
	 $student_results = get_students_search_result($html, $info_student->program_code);
	  	/** @var $student_result StudentSearchResult */
    foreach($student_results as $student_result){
			$cod_per = $student_result->codigo_persona;
			$cod_estu = $student_result->codigo_estudiante;
			$tipo_doc = $student_result->tipo_documento;
			$numero_doc = $student_result->documento;
			$nombre = $student_result->nombre;
			$apellido = $student_result->apellidos;
			echo 'Codigo persona '.$cod_per.'<br />';	
			echo 'Codigo estudiante'.$cod_estu.'<br />';
			echo 'Tipo documento '.$tipo_doc.'<br />';
			echo 'Numero documento '.$numero_doc.'<br />';
			echo 'Nombres '.$nombre.'<br />';
			echo 'Apellidos '.$apellido.'<br />';


			$plan = $student_result->programa;
			$sede = $student_result->sede;
			$mod = $student_result->jornada;
		
			echo 'Plan '.$plan.'<br />';
			echo 'Sede '.$sede.'<br />';
			echo 'Modalidad '.$mod.'<br />';
			echo '<hr />'; 

			$ocurrencia_igual_nombre_diferente_documento="NO";

			$nombre_wsp=str_replace("%20"," ",$st_nombres);
			$apellido_wsp=str_replace("%20"," ",$st_apellidos);

			$apellido_wsp_reg=str_replace("+"," ", $apellido_wsp);

			$info_student_active = [];
			
			if($nombre === $nombre_wsp && $apellido_wsp_reg === $apellido && $numero_doc !== $st_documento){
				echo "CUMPLE CONDICION IGUAL NOMBRE DIFERENTE DOCUMENTO <br>";
				
				$ocurrencia_igual_nombre_diferente_documento="SI";

				
				$info_student = search_student_info_each_program(
					$cod_per, $cod_estu, $tipo_doc, $numero_doc, $nombre, $apellido, $plan, $sede, $mod, $ocurrencia_igual_nombre_diferente_documento);
				
				$info_student_sp = explode(",", $info_student);
			
				$info_student_active = $info_student;

				if($info_student_sp[8] == "ACTIVO" || $info_student_sp[8] == "INACTIVO"){
					return $info_student_active;
				}
			}

			if($numero_doc === $st_documento){
				echo "check id";

				$info_student = search_student_info_each_program(
					$cod_per,$cod_estu,$tipo_doc,$numero_doc,$nombre,$apellido,$plan,$sede,$mod,$ocurrencia_igual_nombre_diferente_documento);
				
					echo 'Info student: '.$info_student;

					$info_student_sp = explode(",", $info_student);

					$info_student_active = $info_student;
				if($info_student_sp[8]=="ACTIVO" || $info_student_sp[8] == "INACTIVO"){
					echo "ENTRA Y VERIFICA ACTIVO";
					
					return $info_student_active;
				}

			} 

			if($info_student_active === []){

				return ",,$st_documento,$st_nombres,$st_apellidos_sp,NO ENCONTRADO";
		   	} 
	   }

	   return $info_student_active;    
}


function search_student_info_each_program($cod_per,$cod_estu,$tipo_doc,$numero_doc,$nombre,$apellido,$plan,$sede,$mod,$ocurrencia_igual_nombre_diferente_documento){
	global $cookie_value;

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

   	echo $respo2;
	
	$dom2 = new domDocument; 

	$dom2->loadHTML($respo2); 
	
    $dom2->preserveWhiteSpace = false; 
   
    $tables = $dom2->getElementsByTagName('table'); 
   
    $rows = $tables->item(0)->getElementsByTagName('tr'); 
   
    $cols = $dom2->getElementsByTagName('td'); 
	$estado = 'INACTIVO';
	$has_cancelled = "NO";
    echo 'El estado es '.$estado.'<br />';
      
	for ($i = 0; $i <= count($cols); $i++) {
	    $periodo_academico = (string)$cols->item($i)->nodeValue;
	    #echo $periodo_academico.'<br />';
	    if(substr_count($periodo_academico,"FEBRERO/2018 - JUNIO/2018") == 1){
	    	//echo 'I '.$i.' '.$periodo_academico;
			$estado = 'ACTIVO';
			echo 'he entrado aqui a activo <br />';
			echo (string)$cols->item($i)->getAttribute('class').' es la clase <br />';
			if((string)$cols->item($i)->getAttribute('class')=="normalRojo"){
				$has_cancelled = "SI";
				echo $has_cancelled.' ha cancelado <br />';
			}
			
	    }
		
	}

	echo '<br>EL ESTADO ES '.$estado.'<br />';
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

	$student_names_arr = read_student_info_csv();

	$list_student = array();
	foreach($student_names_arr as $info_student)
	{
		$info_student_active = search_student_info($info_student);
		array_push($list_student, $info_student_active);
	}

	echo "Trying to write student information <br />";
	write_student_information_to_csv($list_student);
?> 
