
CREATE TABLE IF NOT EXISTS `admin` (
  `name` varchar(255) NOT NULL,
  `tvalue` varchar(255) NOT NULL,
  `ivalue` int(11) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `calendrier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `comment` longtext NOT NULL,
  `unite` longtext DEFAULT NULL,
  `nigend` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `dde_resa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ressource` int(11) DEFAULT NULL,
  `nigend_demandeur` int(11) DEFAULT NULL,
  `unite_demandeur` int(11) DEFAULT NULL,
  `date_dde` datetime DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `motif_dde` longtext DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `lbl_unite` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cal` int(11) DEFAULT 0,
  `date_action` datetime NOT NULL,
  `nigend` int(11) NOT NULL,
  `comment` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `reservation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_seance` int(11) NOT NULL,
  `date_action` datetime NOT NULL,
  `nigend` int(11) NOT NULL,
  `info_nigend` varchar(255) DEFAULT NULL,
  `info_mail` varchar(255) DEFAULT NULL,
  `nigend_origine` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `ressources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `comment` longtext NOT NULL,
  `type` varchar(20) DEFAULT NULL,
  `cal` int(11) DEFAULT NULL,
  `couleur` varchar(255) DEFAULT NULL,
  `cu_admin` varchar(255) DEFAULT NULL,
  `email` varchar(500) DEFAULT NULL,
  `time_modif` timestamp NOT NULL DEFAULT current_timestamp(),
  `purge_cycle` int(3) DEFAULT 0,
  `lier` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `seance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cal` int(11) NOT NULL,
  `id_type` int(11) NOT NULL,
  `id_ressource` int(11) NOT NULL,
  `comment` longtext DEFAULT NULL,
  `nb_pers` int(11) NOT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `date_fin_inscription` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `type_seance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cal` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `couleur` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

ALTER TABLE dde_resa ADD COLUMN IF NOT EXISTS nb_pers int(11) default 0;
ALTER TABLE calendrier ADD COLUMN IF NOT EXISTS visible int(1) default 1;
ALTER TABLE ressources ADD COLUMN IF NOT EXISTS link varchar(255) default NULL;

CREATE TABLE IF NOT EXISTS `users` (
  `nigend` int(11) NOT NULL,
  `uid` varchar(255) NOT NULL,
  `codeUnite` int(11) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `login` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profil` varchar(255) NOT NULL,
  PRIMARY KEY (`nigend`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `services` (
  `codeUnite` int(11) NOT NULL,
  `unite` varchar(255) NOT NULL,
  `mail` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`codeUnite`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ALTER TABLE calendrier CHANGE unite service longtext;
-- ALTER TABLE calendrier CHANGE nigend id_utilisateur longtext;
-- ALTER TABLE dde_resa CHANGE nigend_demandeur id_demandeur int(11);
-- ALTER TABLE dde_resa CHANGE unite_demandeur service_demandeur int(11);
-- ALTER TABLE dde_resa CHANGE lbl_unite lbl_service int(11);
-- ALTER TABLE log CHANGE nigend id_utilisateur int(11);
-- ALTER TABLE reservation CHANGE nigend id_utilisateur int(11);
