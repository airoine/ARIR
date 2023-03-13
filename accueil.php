<?php
/* MIT License
 * Copyright (c) 2023 ARIR - Erwan Goalou
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), 
 * to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, 
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}
echo "<style>
.container {
  position: relative;
  border: 1px solid #4F3F8C; 
  border-radius: 20px;
}

.center {
font-size: 1.2em;
  margin: 0;
  position: absolute;
  top: 50%;
  left: 50%;
  -ms-transform: translate(-50%, -50%);
  transform: translate(-50%, -50%);
}
</style>";

echo '<div class="row">';
echo '<div class="col-xl-3" align=center >';
if (defined('LOGO'))
	echo '<img src="img/'.LOGO.'" style="width:85%">';
else 
	echo '<img src="img/default.png" style="width:85%">';
echo '</div>';

echo '<div class="col-xl-6 alert alert-warning container"  >';
echo '<div class="center"><center>';
echo "Bienvenue dans l'application de réservation et de calendrier.<br>".NAME." Version ".VERSION;
echo "<br>Cliquer sur le bouton 'Menus' pour accéder aux différentes options.";
echo "</center></div>";
echo '</div>';

echo '<div class="col-xl-3" align=center >';
if (defined('LOGO'))
	echo '<img src="img/'.LOGO.'" style="width:85%">';
else 
	echo '<img src="img/default.png" style="width:85%">';
echo '</div>';

echo '</div>';
$row = 1;
?>

