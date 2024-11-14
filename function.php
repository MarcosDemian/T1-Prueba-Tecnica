<?php
include("header.php");

$host = 'localhost';
$user = 'root';
$password = 'CarloslO17'; //Contraseña de mi DB, cambiar!
$dbname = 'db1'; //Nombre de la base de datos, cambiar!

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if (isset($_POST['button'])) { 
  $previousPage = $_SERVER['HTTP_REFERER'];
  header("Location: $previousPage");
  die;
}

// Función para obtener las tareas que superan el monto promedio
function getTasksAboveAverage($conn, $input_month, $input_year) {
    // Consulta para calcular el monto promedio por cada resource_id_assigned
    $query_avg_amount = "
    SELECT t.resource_id_assigned, AVG(tc.amount) AS average_amount
    FROM Task t
    JOIN Task_count tc ON t.ID = tc.Task_ID
    JOIN work_order_item_detail woid ON t.work_order_item_detail_ID = woid.ID
    JOIN work_order_item woi ON woid.work_order_item_id = woi.ID
    JOIN work_order wo ON woi.work_order_id = wo.ID
    WHERE YEAR(wo.created) = ? AND MONTH(wo.created) = ?
    GROUP BY t.resource_id_assigned
    ";

    // Preparar y ejecutar la consulta para obtener el promedio
    if ($stmt = $conn->prepare($query_avg_amount)) {
        $stmt->bind_param("ii", $input_year, $input_month);
        $stmt->execute();
        $result_avg = $stmt->get_result();

        // Verificar si se obtuvieron promedios
        if ($result_avg->num_rows == 0) {
            echo "
              <div class='main_container'>
              <h3 class='subtitle'>No se encontraron recursos para el mes $input_month y año $input_year<h3/>
              <form class='form_back' action='index.php' method='post'>
               <input class='btns' type='submit' value='<- Volver'>
              </form>
              </div>
            ";
            return;
        }

        $output = fopen("tasks_above_average.csv", "w");

        fputcsv($output, [
            "Nombre Completo de Recurso", 
            "Nro de Proyecto", 
            "Fecha de la Orden de Trabajo", 
            "Nro de Tarea", 
            "Precio Unitario de la Tarea", 
            "Cantidad", 
            "Monto", 
            "Nro de Orden de Trabajo"
        ]);

        // Procesar los promedios por cada resource_id_assigned
        while ($avg = $result_avg->fetch_assoc()) {
            $resource_id = $avg['resource_id_assigned'];
            $average_amount = $avg['average_amount'];

            // Consulta para obtener las tareas que superan el monto promedio
            $query_tasks = "
            SELECT 
                r.firstname, 
                r.lastname, 
                wo.project_id, 
                DATE_FORMAT(wo.created, '%m-%d-%Y') AS work_order_created, 
                t.ID AS task_ID, 
                tc.unit_price, 
                tc.count, 
                tc.amount, 
                wo.ID AS work_order_id
            FROM Task t
            JOIN Task_count tc ON t.ID = tc.Task_ID
            JOIN work_order_item_detail woid ON t.work_order_item_detail_ID = woid.ID
            JOIN work_order_item woi ON woid.work_order_item_id = woi.ID
            JOIN work_order wo ON woi.work_order_id = wo.ID
            JOIN Resource r ON t.resource_id_assigned = r.ID
            WHERE t.resource_id_assigned = ? 
            AND YEAR(wo.created) = ? 
            AND MONTH(wo.created) = ? 
            AND tc.amount > ?
            ";

            // Preparar y ejecutar la consulta para obtener las tareas
            if ($stmt2 = $conn->prepare($query_tasks)) {
                $stmt2->bind_param("iiii", $resource_id, $input_year, $input_month, $average_amount);
                $stmt2->execute();
                $result_tasks = $stmt2->get_result();

                while ($task = $result_tasks->fetch_assoc()) {
                    fputcsv($output, [
                        $task['firstname'] . ' ' . $task['lastname'], 
                        $task['project_id'], 
                        $task['work_order_created'], 
                        $task['task_ID'], 
                        $task['unit_price'], 
                        $task['count'], 
                        $task['amount'], 
                        $task['work_order_id'] 
                    ]);
                }

                $stmt2->close();
            }
        }

        fclose($output);
        echo "
          <div class='main_container'>
          <h3 class='subtitle'>Precione el botón para iniciar la descarga:</h3>
          <a class='btns' href='./tasks_above_average.csv'>Descargar .CSV</a>
          <form class='form_back' action='index.php' method='post'>
               <input class='btns' type='submit' value='<- Volver'>
          </form>
          </div>";
        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
    }
}

// Condicion para pasar el mes y año en su correcto formato
if (isset($_POST['date_input'])) {
  $month = $_POST['date_input'];

  list($year, $month) = explode('-', $month);

  $form = $month . '-' . $year;

  $input_month = $month; 
  $input_year = $year; 

getTasksAboveAverage($conn, $input_month, $input_year);
  
} else {
  echo "No se ha seleccionado ningún mes.";
}

$conn->close();
?>