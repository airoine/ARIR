
<!DOCTYPE html>
<html lang="en" >

<head>

  <meta charset="UTF-8">
  
 
  <title>ERROR - 403 Forbidden</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  
  
<style>

* {
    margin: 0;
    padding: 0;
    border: 0;
    font-size: 100%;
    font: inherit;
    vertical-align: baseline;
    box-sizing: border-box;
    color: inherit;
}

body {
    background-image: linear-gradient(45deg, #e8d4be, #045a7c);
    height: 100vh;
}

h1 {
    font-size: 45vw;
    text-align: center;
    position: fixed;
    width: 100vw;
    z-index: 1;
    color: #8A084B;
    text-shadow: 0 0 50px rgba(0, 0, 0, 0.07);
    top: 50%;
    transform: translateY(-50%);
    font-family: "Montserrat", monospace;
}

div {
    background: rgba(15, 14, 14, 0.67);
    width: 70vw;
    position: relative;
    top: 50%;
    transform: translateY(-50%);
    margin: 0 auto;
    padding: 30px 30px 10px;
    box-shadow: 0 0 150px -20px rgba(0, 0, 0, 0.5);
    z-index: 3;
}

P {
    font-family: "Share Tech Mono", monospace;
    color: #f5f5f5;
    margin: 0 0 20px;
    font-size: 17px;
    line-height: 1.2;
}

span {
    color: #f0c674;
}

div a {
    text-decoration: none;
}

b {
    color: #66e6d4;
}


@-webkit-keyframes slide {
    from {
        right: -100px;
        transform: rotate(360deg);
        opacity: 0;
    }
    to {
        right: 15px;
        transform: rotate(0deg);
        opacity: 1;
    }
}

@keyframes slide {
    from {
        right: -100px;
        transform: rotate(360deg);
        opacity: 0;
    }
    to {
        right: 15px;
        transform: rotate(0deg);
        opacity: 1;
    }
}
</style>

  <script>
  window.console = window.console || function(t) {};
</script>

  
  
  <script>
  if (document.location.search.match(/type=embed/gi)) {
    window.parent.postMessage("resize", "*");
  }
</script>


</head>

<body translate="no" >
  <h1>403</h1>
<div><p>> <span>CODE ERREUR</span>: <b>HTTP 403 Forbidden</b></p>
<p>> <span>DESCRIPTION DE L'ERREUR</span>: <b>Accès refusé. Vous n'avez pas la permission d'accéder à cette page sur ce serveur</b></p>
<p>> <span>CAUSE POSSIBLE DE CETTE ERREUR</span>: <b>Vous essayez d'injecter des données non permises, vous essayez d'accéder à une page non autorisée, vous tentez une attaque CSRF (ce n'est pas bien)</b>...</p>
<p>> <span>SI VOUS PENSEZ AVOIR TROUVE UN BUG</span>: <b>Contacter le développeur sur Github</b></p>
<p>> <span>HAVE A NICE DAY.</span></p>
</div>


  
      <script id="rendered-js" >
var str = document.getElementsByTagName('div')[0].innerHTML.toString();
var i = 0;
document.getElementsByTagName('div')[0].innerHTML = "";

setTimeout(function () {
  var se = setInterval(function () {
    i++;
    document.getElementsByTagName('div')[0].innerHTML = str.slice(0, i) + "|";
    if (i == str.length) {
      clearInterval(se);
      document.getElementsByTagName('div')[0].innerHTML = str;
    }
  }, 20);
}, 0);
    </script>

  

</body>

</html>
 
