-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: autopro_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `autopro_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `autopro_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `autopro_db`;

--
-- Table structure for table `service_tasks`
--

DROP TABLE IF EXISTS `service_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `status` enum('TODO','IN_PROGRESS','DONE') DEFAULT 'TODO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_tasks`
--

LOCK TABLES `service_tasks` WRITE;
/*!40000 ALTER TABLE `service_tasks` DISABLE KEYS */;
INSERT INTO `service_tasks` VALUES (1,101,3,'BMW 320d - Ndërrim frenash','TODO','2026-11-03 23:54:57'),(2,102,4,'Audi A4 - Diagnostikim OBD','IN_PROGRESS','2026-11-03 23:58:54'),(3,103,3,'VW Golf 7 - Ndërrim vaj dhe filtra','DONE','2026-11-04 00:02:19'),(4,104,4,'Mercedes C220 - Shërbim klimatizimi','TODO','2026-11-04 00:02:23'),(5,105,3,'Toyota Corolla - Kontroll pezullimi','IN_PROGRESS','2026-11-04 09:08:09'),(6,106,4,'Skoda Octavia - Kalibrim asistimi','DONE','2026-11-04 09:08:13'),(7,107,3,'Ford Focus - Kontroll i përgjithshëm','TODO','2026-11-04 10:14:26');
/*!40000 ALTER TABLE `service_tasks` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-18  4:01:41
