# ARIR


ARIR, Application de Réservation et d’Inscription à des Ressources, est une application web. Elle met à disposition des utilisateurs des calendriers permettant la réservation de ressources (réservation de véhicules, de salles, de matériel, etc.) mais également la possibilité de s’inscrire à des activités (séances de sport, événements, covoiturage, etc.).

Elle est développée en php/html/js/css/mysql. Elle utilise bootstap5.3 (style), bootstrap-select1.14 (style des listes déroulantes), bootstrap-table (style des tableaux), fullcalendar-6.1.4 (les calendriers) Pour la faire fonctionner il faut donc qu’un serveur web soit installé (apache/nginx) avec les modules php et mysql. Un serveur mariadb/mysql doit également être disponible.

L’application a un module d’installation automatique et ne nécessite aucune connaissance de développement ou de base de donnée.

Elle peut fonctionner avec des comptes locaux (gérés dans l’application), mais également via des comptes ldap ou derrière un SSO (lemonldap).

Le code source peut être mutualisé sur un même serveur pour plusieurs instances (un seul code avec plusieurs bases de données pour que plusieurs instance de l’application soit disponible pour différentes utilisations)

Elle a été développée pour un ministère et est utilisée un peu partout en France. Afin que le travail effectué en interne soit valorisé, il a été décidé de la rendre disponible sur GIT avec une licence open-source MIT.
