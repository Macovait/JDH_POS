-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: jakababa_pos
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `reviewed` tinyint(1) DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activity_reviewed_by` (`reviewed_by`),
  KEY `idx_activity_logs_created` (`created_at`),
  KEY `idx_activity_user_date` (`user_id`,`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_activity_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'auth.login',NULL,NULL,'{\"user\":\"admin\"}','2026-03-08 09:31:30',NULL,0,NULL,NULL),(2,1,'auth.login',NULL,NULL,'{\"user\":\"admin\"}','2026-03-08 10:43:22',NULL,0,NULL,NULL),(3,1,'auth.login',NULL,NULL,'{\"user\":\"admin\"}','2026-03-08 10:44:13',NULL,1,1,'2026-03-12 00:21:21'),(4,1,'auth.login',NULL,NULL,'{\"username\":\"admin\",\"ip\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/145.0.0.0 Safari\\/537.36 Edg\\/145.0.0.0\"}','2026-03-08 11:36:45',NULL,0,NULL,NULL),(5,1,'profile_update',NULL,NULL,'{\"description\":\"Updated profile information\",\"ip_address\":\"::1\"}','2026-03-09 23:17:42',NULL,0,NULL,NULL),(6,1,'profile_update',NULL,NULL,'{\"description\":\"Updated profile information\",\"ip_address\":\"::1\"}','2026-03-09 23:18:01',NULL,0,NULL,NULL),(7,1,'settings_update','Updated general settings','::1',NULL,'2026-03-10 16:03:03',NULL,0,NULL,NULL),(8,1,'category_create','Created new category: Baby Clothes','::1',NULL,'2026-03-10 22:54:31',NULL,0,NULL,NULL),(9,1,'category_create','Created new category: Baby Clothes','::1',NULL,'2026-03-10 22:55:24',NULL,0,NULL,NULL),(10,1,'category_create','Created new category: Baby Toys','::1',NULL,'2026-03-10 23:04:26',NULL,0,NULL,NULL),(11,1,'category_create','Created new category: Swimming Costumes','::1',NULL,'2026-03-10 23:06:40',NULL,0,NULL,NULL),(12,1,'category_create','Created new category: Phones','::1',NULL,'2026-03-10 23:18:52',NULL,0,NULL,NULL),(13,1,'category_created','Created new category: Nutrition','::1',NULL,'2026-03-11 20:52:25',NULL,0,NULL,NULL),(14,1,'category_created','Created new category: Electronics','::1',NULL,'2026-03-11 23:00:01',NULL,0,NULL,NULL),(15,1,'draft_created','Created draft sale #5 with 1 items','::1',NULL,'2026-03-12 18:05:53',NULL,0,NULL,NULL),(16,1,'draft_created','Created draft sale #6 with 1 items','::1',NULL,'2026-03-12 18:06:06',NULL,0,NULL,NULL),(17,1,'draft_created','Created draft sale #7 with 1 items','::1',NULL,'2026-03-12 18:10:42',NULL,0,NULL,NULL),(18,1,'draft_deleted','Deleted draft sale #7','::1',NULL,'2026-03-12 18:11:25',NULL,0,NULL,NULL),(19,1,'draft_created','Created draft sale #8 with 1 items','::1',NULL,'2026-03-12 18:14:52',NULL,0,NULL,NULL),(20,1,'draft_deleted','Deleted draft sale #8','::1',NULL,'2026-03-12 18:15:16',NULL,0,NULL,NULL),(21,1,'draft_created','Created draft sale #9 with 3 items','::1',NULL,'2026-03-12 18:16:42',NULL,0,NULL,NULL),(22,1,'draft_deleted','Deleted draft sale #9','::1',NULL,'2026-03-12 18:16:54',NULL,0,NULL,NULL),(23,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 07:41:03',NULL,0,NULL,NULL),(24,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 07:41:21',NULL,0,NULL,NULL),(25,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 07:41:27',NULL,0,NULL,NULL),(26,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 07:41:52',NULL,0,NULL,NULL),(27,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 07:42:13',NULL,0,NULL,NULL),(28,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 07:43:00',NULL,0,NULL,NULL),(29,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 07:43:13',NULL,0,NULL,NULL),(30,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 07:47:16',NULL,0,NULL,NULL),(31,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 07:53:15',NULL,0,NULL,NULL),(32,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 07:53:44',NULL,0,NULL,NULL),(33,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 08:16:05',NULL,0,NULL,NULL),(34,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 08:16:35',NULL,0,NULL,NULL),(35,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 08:16:50',NULL,0,NULL,NULL),(36,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 08:16:57',NULL,0,NULL,NULL),(37,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 08:17:19',NULL,0,NULL,NULL),(38,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 08:25:58',NULL,0,NULL,NULL),(39,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 08:54:44',NULL,0,NULL,NULL),(40,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 08:55:01',NULL,0,NULL,NULL),(41,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 09:14:43',NULL,0,NULL,NULL),(42,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 09:40:10',NULL,0,NULL,NULL),(43,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 12:27:52',NULL,0,NULL,NULL),(44,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 12:38:11',NULL,0,NULL,NULL),(45,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 12:41:53',NULL,0,NULL,NULL),(46,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 12:42:05',NULL,0,NULL,NULL),(47,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 12:45:17',NULL,0,NULL,NULL),(48,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 12:47:49',NULL,0,NULL,NULL),(49,1,'branch_switch','Switched to branch: Kisumu Branch in reports','::1','{\"description\":\"Switched to branch: Kisumu Branch in reports\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 13:01:28',NULL,0,NULL,NULL),(50,1,'branch_switch','Switched to branch: Main Branch in reports','::1','{\"description\":\"Switched to branch: Main Branch in reports\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 13:01:35',NULL,0,NULL,NULL),(51,1,'branch_switch','Switched to branch: Mombasa Branch in reports','::1','{\"description\":\"Switched to branch: Mombasa Branch in reports\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 13:01:41',NULL,0,NULL,NULL),(52,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:03:18',NULL,0,NULL,NULL),(53,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:08:39',NULL,0,NULL,NULL),(54,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:13:42',NULL,0,NULL,NULL),(55,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 14:13:46',NULL,0,NULL,NULL),(56,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:13:52',NULL,0,NULL,NULL),(57,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:13:57',NULL,0,NULL,NULL),(58,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:14:00',NULL,0,NULL,NULL),(59,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:14:09',NULL,0,NULL,NULL),(60,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:14:15',NULL,0,NULL,NULL),(61,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:14:22',NULL,0,NULL,NULL),(62,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:14:25',NULL,0,NULL,NULL),(63,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 14:14:34',NULL,0,NULL,NULL),(64,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:14:40',NULL,0,NULL,NULL),(65,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:18:36',NULL,0,NULL,NULL),(66,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:18:42',NULL,0,NULL,NULL),(67,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:18:47',NULL,0,NULL,NULL),(68,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:21:21',NULL,0,NULL,NULL),(69,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 14:21:33',NULL,0,NULL,NULL),(70,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:21:36',NULL,0,NULL,NULL),(71,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:21:39',NULL,0,NULL,NULL),(72,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:26:44',NULL,0,NULL,NULL),(73,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 14:34:46',NULL,0,NULL,NULL),(74,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:40:59',NULL,0,NULL,NULL),(75,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:41:42',NULL,0,NULL,NULL),(76,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:41:46',NULL,0,NULL,NULL),(77,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:41:49',NULL,0,NULL,NULL),(78,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:45:03',NULL,0,NULL,NULL),(79,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 14:45:06',NULL,0,NULL,NULL),(80,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:45:08',NULL,0,NULL,NULL),(81,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:45:10',NULL,0,NULL,NULL),(82,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:51:31',NULL,0,NULL,NULL),(83,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:51:41',NULL,0,NULL,NULL),(84,1,'branch_switch','Switched to branch: Mombasa Branch','::1','{\"description\":\"Switched to branch: Mombasa Branch\",\"branch_id\":3,\"branch_name\":\"Mombasa Branch\"}','2026-03-14 14:52:28',NULL,0,NULL,NULL),(85,1,'branch_switch','Switched to branch: Kisumu Branch','::1','{\"description\":\"Switched to branch: Kisumu Branch\",\"branch_id\":4,\"branch_name\":\"Kisumu Branch\"}','2026-03-14 14:52:34',NULL,0,NULL,NULL),(86,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:52:38',NULL,0,NULL,NULL),(87,1,'sale.completed','sale.completed','::1','{\"sale_id\":\"21\",\"total\":324.8,\"items\":3,\"payment_method\":\"cash\"}','2026-03-14 14:53:33',NULL,0,NULL,NULL),(88,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 14:54:28',NULL,0,NULL,NULL),(89,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 15:03:32',NULL,0,NULL,NULL),(90,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 15:03:47',NULL,0,NULL,NULL),(91,1,'sale_complete','Completed sale #INV-20260314-0003 for KSh 58.00','::1',NULL,'2026-03-14 15:21:23',NULL,0,NULL,NULL),(92,1,'sale_complete','Completed sale #INV-20260314-0004 for KSh 58.00','::1',NULL,'2026-03-14 15:21:47',NULL,0,NULL,NULL),(93,1,'sale_complete','Completed sale #INV-20260314-0005 for KSh 58.00','::1',NULL,'2026-03-14 15:22:32',NULL,0,NULL,NULL),(94,1,'sale_complete','Completed sale #INV-20260314-0006 for KSh 58.00','::1',NULL,'2026-03-14 15:26:00',NULL,0,NULL,NULL),(95,1,'branch_switch','Switched to branch: Kisii','::1','{\"description\":\"Switched to branch: Kisii\",\"branch_id\":2,\"branch_name\":\"Kisii\"}','2026-03-14 15:27:22',NULL,0,NULL,NULL),(96,1,'sale_complete','Completed sale #INV-20260314-0007 for KSh 127.60','::1',NULL,'2026-03-14 15:28:24',NULL,0,NULL,NULL),(97,1,'sale_complete','Completed sale #INV-20260314-0008 for KSh 150.80','::1',NULL,'2026-03-14 15:28:57',NULL,0,NULL,NULL),(98,1,'sale_complete','Completed sale #INV-20260314-0009 for KSh 116.00','::1',NULL,'2026-03-14 15:31:54',NULL,0,NULL,NULL),(99,1,'sale_complete','Completed sale #INV-20260314-0010 for KSh 116.00','::1',NULL,'2026-03-14 15:38:26',NULL,0,NULL,NULL),(100,1,'sale_complete','Completed sale #INV-20260314-0011 for KSh 69.60','::1',NULL,'2026-03-14 15:38:55',NULL,0,NULL,NULL),(101,1,'sale_complete','Completed sale #INV-20260314-0012 for KSh 580.00','::1',NULL,'2026-03-14 15:41:17',NULL,0,NULL,NULL),(102,1,'sale_complete','Completed sale #INV-20260314-0013 for KSh 232.00','::1',NULL,'2026-03-14 15:41:38',NULL,0,NULL,NULL),(103,1,'sale_complete','Completed sale #INV-20260314-0014 for KSh 580.00','::1',NULL,'2026-03-14 19:56:39',NULL,0,NULL,NULL),(104,1,'sale_complete','Completed sale #INV-20260314-0015 for KSh 580.00','::1',NULL,'2026-03-14 19:57:17',NULL,0,NULL,NULL),(105,1,'branch_switch','Switched to branch: Main Branch','::1','{\"description\":\"Switched to branch: Main Branch\",\"branch_id\":1,\"branch_name\":\"Main Branch\"}','2026-03-14 20:12:35',NULL,0,NULL,NULL),(106,1,'return_created','Created return #R20260314-323 for KSh 400.00','::1',NULL,'2026-03-14 21:47:53',NULL,0,NULL,NULL),(107,1,'return_created','Created return #R20260314-992 for KSh 300.00','::1',NULL,'2026-03-14 21:48:31',NULL,0,NULL,NULL),(108,1,'return_created','Created return #R20260314-734 for KSh 300.00','::1',NULL,'2026-03-14 21:51:30',NULL,0,NULL,NULL),(109,1,'return_created','Created return #R20260314-643 for KSh 100.00','::1',NULL,'2026-03-14 22:00:41',NULL,0,NULL,NULL),(110,1,'return_created','Created return #R20260314-147 for KSh 200.00','::1',NULL,'2026-03-14 22:07:07',NULL,0,NULL,NULL),(111,1,'return_created','Created return #R20260314-040 for KSh 300.00','::1',NULL,'2026-03-14 22:15:40',NULL,0,NULL,NULL),(112,1,'return_created','Created return #R20260314-258 for KSh 130.00','::1',NULL,'2026-03-14 22:20:40',NULL,0,NULL,NULL),(113,1,'return_created','Created return #R20260314-557 for KSh 100.00','::1',NULL,'2026-03-14 22:35:29',NULL,0,NULL,NULL),(114,1,'return_created','Created return #R20260314-633 for KSh 60.00','::1',NULL,'2026-03-14 22:39:56',NULL,0,NULL,NULL),(115,1,'return_created','Created return #R20260314-716 for KSh 400.00','::1',NULL,'2026-03-14 22:44:38',NULL,0,NULL,NULL),(116,1,'return_created','Created return #R20260314-393 for KSh 400.00','::1',NULL,'2026-03-14 22:53:56',NULL,0,NULL,NULL),(117,1,'return_created','Created return #R20260315-201 for KSh 200.00','::1',NULL,'2026-03-14 23:06:04',NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs_archive`
--

DROP TABLE IF EXISTS `activity_logs_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `notes` text DEFAULT NULL,
  `reviewed` tinyint(1) DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs_archive`
--

LOCK TABLES `activity_logs_archive` WRITE;
/*!40000 ALTER TABLE `activity_logs_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs_archive` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `branch_customers`
--

DROP TABLE IF EXISTS `branch_customers`;
/*!50001 DROP VIEW IF EXISTS `branch_customers`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `branch_customers` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `phone`,
  1 AS `email`,
  1 AS `loyalty_points`,
  1 AS `active`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `status`,
  1 AS `deleted_at`,
  1 AS `deleted_by`,
  1 AS `credit_limit`,
  1 AS `loyalty_tier`,
  1 AS `total_spent`,
  1 AS `last_purchase`,
  1 AS `branch_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `branch_products`
--

DROP TABLE IF EXISTS `branch_products`;
/*!50001 DROP VIEW IF EXISTS `branch_products`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `branch_products` AS SELECT
 1 AS `id`,
  1 AS `category_id`,
  1 AS `name`,
  1 AS `sku`,
  1 AS `price`,
  1 AS `selling_price`,
  1 AS `cost_price`,
  1 AS `active`,
  1 AS `image`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `status`,
  1 AS `tax_rate_id`,
  1 AS `deleted_at`,
  1 AS `deleted_by`,
  1 AS `branch_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `branch_suppliers`
--

DROP TABLE IF EXISTS `branch_suppliers`;
/*!50001 DROP VIEW IF EXISTS `branch_suppliers`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `branch_suppliers` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `contact`,
  1 AS `phone`,
  1 AS `email`,
  1 AS `active`,
  1 AS `address`,
  1 AS `tax_id`,
  1 AS `payment_terms`,
  1 AS `created_at`,
  1 AS `status`,
  1 AS `updated_at`,
  1 AS `deleted_at`,
  1 AS `deleted_by`,
  1 AS `branch_id` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `code` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `location` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Main Branch','','Nairobi CBD','+254700000000','','',1,'2026-03-08 19:34:27',NULL,16.00,NULL,'08:00:00','20:00:00'),(2,'Kisii','','','+254759714022','wickymacochiz80@gmail.com','Shacaz Baby Shop',1,'2026-03-08 19:34:36',NULL,0.00,NULL,NULL,NULL),(3,'Mombasa Branch','','Mombasa CBD','+254711222333',NULL,NULL,1,'2026-03-12 20:00:29',NULL,0.00,NULL,NULL,NULL),(4,'Kisumu Branch','','Kisumu CBD','+254733444555',NULL,NULL,1,'2026-03-12 20:00:29',NULL,0.00,NULL,NULL,NULL);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT 'tag',
  `image` varchar(255) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `deleted_by` (`deleted_by`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_category_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_category_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,NULL,'Beverages',NULL,'#3B82F6','tag',NULL,NULL,NULL,'2026-03-10 22:44:33','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(2,NULL,'Snacks',NULL,'#3B82F6','tag',NULL,NULL,NULL,'2026-03-10 22:44:33','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(3,NULL,'Household',NULL,'#3B82F6','tag',NULL,NULL,NULL,'2026-03-10 22:44:33','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(4,NULL,'Baby Clothes','Well fit','#771818','light-bulb',NULL,1,1,'2026-03-10 22:54:31','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(6,NULL,'Baby Toys','','#3b82f6','tag',NULL,1,1,'2026-03-10 23:04:26','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(7,NULL,'Swimming Costumes','Affordable','#06b6d4','home','/uploads/categories/category_1773184000_69b0a400c5877.webp',1,1,'2026-03-10 23:06:40','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(8,NULL,'Phones','Balcks','#f97316','map-pin','/uploads/categories/category_1773184732_69b0a6dcc4c8e.jpg',1,1,'2026-03-10 23:18:52','active',0,NULL,NULL,'2026-03-12 00:52:17',NULL,NULL,NULL),(9,NULL,'Nutrition','Wellness','#10b981','light-bulb','/uploads/categories/category_1773262345_69b1d609edad4.jpg',1,1,'2026-03-11 20:52:25','active',0,NULL,NULL,'2026-03-12 00:52:25',NULL,NULL,NULL),(10,NULL,'Electronics','Appliance','#f97316','home',NULL,1,1,'2026-03-11 23:00:01','active',0,NULL,NULL,'2026-03-12 03:00:01',NULL,NULL,NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_addresses`
--

DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `address_type` enum('billing','shipping','home','work','other') DEFAULT 'shipping',
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Kenya',
  `phone` varchar(40) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_addresses_customer` (`customer_id`),
  CONSTRAINT `customer_addresses_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_addresses`
--

LOCK TABLES `customer_addresses` WRITE;
/*!40000 ALTER TABLE `customer_addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `customer_loyalty`
--

DROP TABLE IF EXISTS `customer_loyalty`;
/*!50001 DROP VIEW IF EXISTS `customer_loyalty`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `customer_loyalty` AS SELECT
 1 AS `id`,
  1 AS `customer_name`,
  1 AS `phone`,
  1 AS `email`,
  1 AS `loyalty_points`,
  1 AS `total_transactions`,
  1 AS `total_spent`,
  1 AS `average_transaction_value`,
  1 AS `last_purchase_date` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `loyalty_tier` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `total_spent` decimal(15,2) DEFAULT 0.00,
  `last_purchase` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_email` (`email`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Walk-in','000','',0,1,'2026-03-08 08:45:52',NULL,1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL),(2,'Jane Doe','+254711111111','jane@example.com',350,1,'2026-03-08 08:45:52','2026-03-13 00:09:26',1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL),(3,'Wycliffe Bunde','+254759714022','wickymacochiz80@gmail.com',200,1,'2026-03-08 10:26:49',NULL,1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL),(4,'John Smith','+254722333444','john.smith@email.com',150,1,'2026-03-12 17:00:29',NULL,1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL),(5,'Mary Johnson','+254733555666','mary.j@email.com',75,1,'2026-03-12 17:00:29',NULL,1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL),(6,'Peter Kamau','+254744666777','peter.k@email.com',0,1,'2026-03-12 17:00:29',NULL,1,NULL,NULL,0.00,NULL,'bronze',0.00,NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `daily_sales_summary`
--

DROP TABLE IF EXISTS `daily_sales_summary`;
/*!50001 DROP VIEW IF EXISTS `daily_sales_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `daily_sales_summary` AS SELECT
 1 AS `sale_date`,
  1 AS `branch_name`,
  1 AS `branch_id`,
  1 AS `transaction_count`,
  1 AS `total_sales`,
  1 AS `total_discounts`,
  1 AS `average_transaction_value`,
  1 AS `unique_customers` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `discounts`
--

DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `type` enum('fixed','percent') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `active` tinyint(4) DEFAULT 1,
  `description` text DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `min_purchase` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT 5,
  `applicable_products` varchar(20) DEFAULT 'all',
  `product_ids` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `discounts_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_discount_value` CHECK (`type` = 'percent' and `value` <= 100 or `type` = 'fixed' and `value` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discounts`
--

LOCK TABLES `discounts` WRITE;
/*!40000 ALTER TABLE `discounts` DISABLE KEYS */;
INSERT INTO `discounts` VALUES (1,'Offer Discount','percent',25.00,1,'4th and 5th April 2026','2026-04-04','2026-04-05',0.00,NULL,NULL,5,'all',NULL,NULL,NULL,'2026-03-08 19:19:42');
/*!40000 ALTER TABLE `discounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `draft_items`
--

DROP TABLE IF EXISTS `draft_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `draft_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `draft_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `draft_items`
--

LOCK TABLES `draft_items` WRITE;
/*!40000 ALTER TABLE `draft_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `draft_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drafts`
--

DROP TABLE IF EXISTS `drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drafts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `discount_type` varchar(20) DEFAULT 'fixed',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drafts`
--

LOCK TABLES `drafts` WRITE;
/*!40000 ALTER TABLE `drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `color` varchar(20) DEFAULT '#FBBF24',
  `icon` varchar(50) DEFAULT 'fas fa-tag',
  `budget` decimal(15,2) DEFAULT 0.00,
  `budget_period` enum('monthly','quarterly','yearly') DEFAULT 'monthly',
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `expense_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'Rent','Monthly rent payments',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(2,'Utilities','Electricity, water, internet bills',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(3,'Salaries','Employee salaries and wages',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(4,'Marketing','Advertising and promotional expenses',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(5,'Maintenance','Repairs and maintenance',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(6,'Transport','Shipping and delivery costs',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(7,'Office Supplies','Stationery and office materials',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(8,'Insurance','Business insurance premiums',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(9,'Licenses','Business permits and licenses',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(10,'Miscellaneous','Other expenses',NULL,'2026-03-12 16:55:15',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(11,'Rent','Monthly rent payments',NULL,'2026-03-14 04:50:14',NULL,'#10B981','fas fa-building',0.00,'monthly',1),(12,'Utilities','Electricity, water, internet',NULL,'2026-03-14 04:50:14',NULL,'#FBBF24','fas fa-bolt',0.00,'monthly',1),(13,'Salaries','Employee salaries',NULL,'2026-03-14 04:50:14',NULL,'#EF4444','fas fa-users',0.00,'monthly',1),(14,'Marketing','Advertising and promotions',NULL,'2026-03-14 04:50:14',NULL,'#8B5CF6','fas fa-chart-line',0.00,'monthly',1),(15,'Maintenance','Repairs and maintenance',NULL,'2026-03-14 04:50:14',NULL,'#F97316','fas fa-tools',0.00,'monthly',1),(16,'Transport','Shipping and delivery',NULL,'2026-03-14 04:50:14',NULL,'#3B82F6','fas fa-truck',0.00,'monthly',1),(17,'Office Supplies','Stationery and materials',NULL,'2026-03-14 04:50:14',NULL,'#14B8A6','fas fa-box',0.00,'monthly',1),(18,'Insurance','Business insurance',NULL,'2026-03-14 04:50:14',NULL,'#EC4899','fas fa-shield-alt',0.00,'monthly',1),(19,'Licenses','Permits and licenses',NULL,'2026-03-14 04:50:14',NULL,'#6B7280','fas fa-file-contract',0.00,'monthly',1),(20,'Miscellaneous','Other expenses',NULL,'2026-03-14 04:50:14',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(21,'Rent','Monthly rent payments',NULL,'2026-03-14 04:56:55',NULL,'#10B981','fas fa-building',0.00,'monthly',1),(22,'Utilities','Electricity, water, internet',NULL,'2026-03-14 04:56:55',NULL,'#FBBF24','fas fa-bolt',0.00,'monthly',1),(23,'Salaries','Employee salaries',NULL,'2026-03-14 04:56:55',NULL,'#EF4444','fas fa-users',0.00,'monthly',1),(24,'Marketing','Advertising and promotions',NULL,'2026-03-14 04:56:55',NULL,'#8B5CF6','fas fa-chart-line',0.00,'monthly',1),(25,'Maintenance','Repairs and maintenance',NULL,'2026-03-14 04:56:55',NULL,'#F97316','fas fa-tools',0.00,'monthly',1),(26,'Transport','Shipping and delivery',NULL,'2026-03-14 04:56:55',NULL,'#3B82F6','fas fa-truck',0.00,'monthly',1),(27,'Office Supplies','Stationery and materials',NULL,'2026-03-14 04:56:55',NULL,'#14B8A6','fas fa-box',0.00,'monthly',1),(28,'Insurance','Business insurance',NULL,'2026-03-14 04:56:55',NULL,'#EC4899','fas fa-shield-alt',0.00,'monthly',1),(29,'Licenses','Permits and licenses',NULL,'2026-03-14 04:56:55',NULL,'#6B7280','fas fa-file-contract',0.00,'monthly',1),(30,'Miscellaneous','Other expenses',NULL,'2026-03-14 04:56:55',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(31,'Rent','Monthly rent payments',NULL,'2026-03-14 04:59:12',NULL,'#10B981','fas fa-building',0.00,'monthly',1),(32,'Utilities','Electricity, water, internet',NULL,'2026-03-14 04:59:12',NULL,'#FBBF24','fas fa-bolt',0.00,'monthly',1),(33,'Salaries','Employee salaries',NULL,'2026-03-14 04:59:12',NULL,'#EF4444','fas fa-users',0.00,'monthly',1),(34,'Marketing','Advertising and promotions',NULL,'2026-03-14 04:59:12',NULL,'#8B5CF6','fas fa-chart-line',0.00,'monthly',1),(35,'Maintenance','Repairs and maintenance',NULL,'2026-03-14 04:59:12',NULL,'#F97316','fas fa-tools',0.00,'monthly',1),(36,'Transport','Shipping and delivery',NULL,'2026-03-14 04:59:12',NULL,'#3B82F6','fas fa-truck',0.00,'monthly',1),(37,'Office Supplies','Stationery and materials',NULL,'2026-03-14 04:59:12',NULL,'#14B8A6','fas fa-box',0.00,'monthly',1),(38,'Insurance','Business insurance',NULL,'2026-03-14 04:59:12',NULL,'#EC4899','fas fa-shield-alt',0.00,'monthly',1),(39,'Licenses','Permits and licenses',NULL,'2026-03-14 04:59:12',NULL,'#6B7280','fas fa-file-contract',0.00,'monthly',1),(40,'Miscellaneous','Other expenses',NULL,'2026-03-14 04:59:12',NULL,'#FBBF24','fas fa-tag',0.00,'monthly',1),(41,'Rent',NULL,NULL,'2026-03-14 06:43:14',NULL,'#EF4444','building',0.00,'monthly',1),(42,'Utilities',NULL,NULL,'2026-03-14 06:43:14',NULL,'#F59E0B','bolt',0.00,'monthly',1),(43,'Salaries',NULL,NULL,'2026-03-14 06:43:14',NULL,'#10B981','users',0.00,'monthly',1),(44,'Marketing',NULL,NULL,'2026-03-14 06:43:14',NULL,'#8B5CF6','megaphone',0.00,'monthly',1),(45,'Maintenance',NULL,NULL,'2026-03-14 06:43:14',NULL,'#3B82F6','wrench',0.00,'monthly',1),(46,'Transport',NULL,NULL,'2026-03-14 06:43:14',NULL,'#EC4899','truck',0.00,'monthly',1),(47,'Office Supplies',NULL,NULL,'2026-03-14 06:43:14',NULL,'#6B7280','box',0.00,'monthly',1),(48,'Other',NULL,NULL,'2026-03-14 06:43:14',NULL,'#374151','folder',0.00,'monthly',1),(49,'Rent',NULL,NULL,'2026-03-14 06:44:56',NULL,'#EF4444','building',0.00,'monthly',1),(50,'Utilities',NULL,NULL,'2026-03-14 06:44:56',NULL,'#F59E0B','bolt',0.00,'monthly',1),(51,'Salaries',NULL,NULL,'2026-03-14 06:44:56',NULL,'#10B981','users',0.00,'monthly',1),(52,'Marketing',NULL,NULL,'2026-03-14 06:44:56',NULL,'#8B5CF6','megaphone',0.00,'monthly',1),(53,'Maintenance',NULL,NULL,'2026-03-14 06:44:56',NULL,'#3B82F6','wrench',0.00,'monthly',1),(54,'Transport',NULL,NULL,'2026-03-14 06:44:56',NULL,'#EC4899','truck',0.00,'monthly',1),(55,'Office Supplies',NULL,NULL,'2026-03-14 06:44:56',NULL,'#6B7280','box',0.00,'monthly',1),(56,'Other',NULL,NULL,'2026-03-14 06:44:56',NULL,'#374151','folder',0.00,'monthly',1);
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','mpesa','cheque') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `vendor` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_branch` (`branch_id`),
  KEY `idx_expenses_category` (`category_id`),
  KEY `idx_expenses_status` (`status`),
  KEY `idx_expenses_branch_date` (`branch_id`,`expense_date`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,1,1,50000.00,'March Rent','2026-03-01','bank_transfer',NULL,NULL,NULL,0,1,'2026-03-12 17:00:29','2026-03-12 22:47:32',NULL,NULL,1,'2026-03-13 01:47:32','Insufficient funds at the moment',0.00,NULL,'pending'),(2,2,1,8500.50,'Electricity bill','2026-03-05','mpesa',NULL,NULL,NULL,0,1,'2026-03-12 17:00:29',NULL,NULL,NULL,NULL,NULL,NULL,0.00,NULL,'pending'),(3,3,1,120000.00,'March salaries','2026-03-25','bank_transfer',NULL,NULL,NULL,0,1,'2026-03-12 17:00:29',NULL,NULL,NULL,NULL,NULL,NULL,0.00,NULL,'pending'),(4,4,2,6000.00,'Marketing budget','2026-03-12','mpesa','001154',NULL,'Approved',1,1,'2026-03-12 22:35:29','2026-03-12 22:46:30',1,'2026-03-13 01:46:30',NULL,NULL,NULL,0.00,'Wycliffe','pending'),(5,7,1,1000.00,'Food','2026-03-12','cash','',NULL,'',1,1,'2026-03-12 22:43:27','2026-03-12 22:46:16',1,'2026-03-13 01:46:16',NULL,NULL,NULL,0.00,'Dann','pending'),(6,1,1,8500.00,'Pay rent','2026-03-13','bank_transfer','',NULL,'Kindly approve',1,1,'2026-03-13 13:47:34','2026-03-13 13:47:49',1,'2026-03-13 16:47:49',NULL,NULL,NULL,0.00,'Wycliffe','pending');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `financial_summary`
--

DROP TABLE IF EXISTS `financial_summary`;
/*!50001 DROP VIEW IF EXISTS `financial_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `financial_summary` AS SELECT
 1 AS `date`,
  1 AS `type`,
  1 AS `amount`,
  1 AS `count` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_id`,`branch_id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_inventory_stock` (`stock`),
  KEY `idx_inventory_stock_level` (`stock`,`reorder_level`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `check_stock_non_negative` CHECK (`stock` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES (1,1,31,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 15:38:55','2026-03-14 05:07:08'),(1,2,4,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 07:12:22','2026-03-14 07:12:22'),(1,3,3,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 05:18:34','2026-03-14 05:18:34'),(2,1,0,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 15:41:38','2026-03-14 05:07:08'),(2,2,10,0,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 07:13:48','2026-03-14 05:07:08'),(3,1,13,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 19:57:17','2026-03-14 05:07:08'),(4,1,0,5,NULL,NULL,NULL,NULL,0,NULL,'2026-03-14 14:53:33','2026-03-14 05:07:08'),(4,2,0,0,NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-03-14 05:07:08');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER inventory_audit_update AFTER UPDATE ON inventory
FOR EACH ROW
BEGIN
    IF OLD.stock != NEW.stock THEN
        INSERT INTO inventory_logs (product_id, branch_id, old_stock, new_stock, change_amount, user_id, created_at, notes)
        VALUES (NEW.product_id, NEW.branch_id, OLD.stock, NEW.stock, NEW.stock - OLD.stock, @current_user_id, NOW(), 'Stock adjustment');
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `old_stock` int(11) DEFAULT 0,
  `new_stock` int(11) DEFAULT 0,
  `change_amount` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `branch_id` (`branch_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
INSERT INTO `inventory_logs` VALUES (1,2,1,40,39,-1,'Stock adjustment',NULL,'2026-03-13 07:28:35'),(2,2,1,39,38,-1,'Stock adjustment',NULL,'2026-03-13 11:08:27'),(3,4,1,10,9,-1,'Stock adjustment',NULL,'2026-03-13 11:08:27'),(4,2,1,38,37,-1,'Stock adjustment',NULL,'2026-03-13 11:29:07'),(5,1,1,52,49,-3,'Stock adjustment',NULL,'2026-03-13 11:29:07'),(6,4,1,9,8,-1,'Stock adjustment',NULL,'2026-03-13 11:31:19'),(7,2,1,37,36,-1,'Stock adjustment',NULL,'2026-03-13 11:31:19'),(8,4,1,8,7,-1,'Stock adjustment',NULL,'2026-03-13 11:51:52'),(9,2,1,36,35,-1,'Stock adjustment',NULL,'2026-03-13 11:51:52'),(10,3,1,30,25,-5,'Stock adjustment',NULL,'2026-03-13 11:51:52'),(11,1,1,49,45,-4,'Stock adjustment',NULL,'2026-03-13 11:51:52'),(12,2,1,35,34,-1,'Stock adjustment',NULL,'2026-03-13 11:59:27'),(13,4,1,7,6,-1,'Stock adjustment',NULL,'2026-03-13 11:59:27'),(14,2,1,34,33,-1,'Stock adjustment',NULL,'2026-03-13 13:42:19'),(15,4,1,6,3,-3,'Stock adjustment',NULL,'2026-03-13 13:42:19'),(16,1,1,45,41,-4,'Stock adjustment',NULL,'2026-03-13 13:42:20'),(17,1,1,41,39,-2,'Stock adjustment',NULL,'2026-03-13 15:09:17'),(18,3,1,25,24,-1,'Stock adjustment',NULL,'2026-03-13 15:09:17'),(19,2,1,33,30,-3,'Stock adjustment',NULL,'2026-03-13 15:09:17'),(20,4,1,3,2,-1,'Stock adjustment',NULL,'2026-03-13 15:09:17'),(21,2,1,30,29,-1,'Stock adjustment',NULL,'2026-03-13 18:29:00'),(22,4,1,2,1,-1,'Stock adjustment',NULL,'2026-03-13 18:29:00'),(23,1,1,39,38,-1,'Stock adjustment',NULL,'2026-03-13 18:29:00'),(24,2,1,29,28,-1,'Stock adjustment',NULL,'2026-03-13 19:13:29'),(25,1,3,0,3,3,'Initial stock setup',1,'2026-03-14 05:18:34'),(26,2,1,28,24,-4,'Stock adjustment',NULL,'2026-03-14 07:10:19'),(27,1,1,38,37,-1,'Stock adjustment',NULL,'2026-03-14 07:10:19'),(28,1,2,0,4,4,'Initial stock setup',1,'2026-03-14 07:12:22'),(29,2,2,0,10,10,'Stock adjustment',NULL,'2026-03-14 07:13:48'),(30,2,2,0,10,10,'Manual update',1,'2026-03-14 07:13:48'),(31,2,1,24,23,-1,'Stock adjustment',NULL,'2026-03-14 14:53:33'),(32,4,1,1,0,-1,'Stock adjustment',NULL,'2026-03-14 14:53:33'),(33,1,1,37,36,-1,'Stock adjustment',NULL,'2026-03-14 14:53:33'),(34,2,1,23,22,-1,'Stock adjustment',NULL,'2026-03-14 15:21:23'),(35,2,1,23,22,-1,'Sale #INV-20260314-0003',1,'2026-03-14 15:21:23'),(36,2,1,22,21,-1,'Stock adjustment',NULL,'2026-03-14 15:21:47'),(37,2,1,22,21,-1,'Sale #INV-20260314-0004',1,'2026-03-14 15:21:47'),(38,2,1,21,20,-1,'Stock adjustment',NULL,'2026-03-14 15:22:32'),(39,2,1,21,20,-1,'Sale #INV-20260314-0005',1,'2026-03-14 15:22:32'),(40,2,1,20,19,-1,'Stock adjustment',NULL,'2026-03-14 15:26:00'),(41,2,1,20,19,-1,'Sale #INV-20260314-0006',1,'2026-03-14 15:26:00'),(42,2,1,19,18,-1,'Stock adjustment',NULL,'2026-03-14 15:28:24'),(43,2,1,19,18,-1,'Sale #INV-20260314-0007',1,'2026-03-14 15:28:24'),(44,1,1,36,34,-2,'Stock adjustment',NULL,'2026-03-14 15:28:24'),(45,1,1,36,34,-2,'Sale #INV-20260314-0007',1,'2026-03-14 15:28:24'),(46,1,1,34,33,-1,'Stock adjustment',NULL,'2026-03-14 15:28:57'),(47,1,1,34,33,-1,'Sale #INV-20260314-0008',1,'2026-03-14 15:28:57'),(48,3,1,24,23,-1,'Stock adjustment',NULL,'2026-03-14 15:28:57'),(49,3,1,24,23,-1,'Sale #INV-20260314-0008',1,'2026-03-14 15:28:57'),(50,2,1,18,16,-2,'Stock adjustment',NULL,'2026-03-14 15:31:54'),(51,2,1,18,16,-2,'Sale #INV-20260314-0009',1,'2026-03-14 15:31:54'),(52,2,1,16,14,-2,'Stock adjustment',NULL,'2026-03-14 15:38:26'),(53,2,1,16,14,-2,'Sale #INV-20260314-0010',1,'2026-03-14 15:38:26'),(54,1,1,33,31,-2,'Stock adjustment',NULL,'2026-03-14 15:38:55'),(55,1,1,33,31,-2,'Sale #INV-20260314-0011',1,'2026-03-14 15:38:55'),(56,2,1,14,4,-10,'Stock adjustment',NULL,'2026-03-14 15:41:17'),(57,2,1,14,4,-10,'Sale #INV-20260314-0012',1,'2026-03-14 15:41:17'),(58,2,1,4,0,-4,'Stock adjustment',NULL,'2026-03-14 15:41:38'),(59,2,1,4,0,-4,'Sale #INV-20260314-0013',1,'2026-03-14 15:41:38'),(60,3,1,23,18,-5,'Stock adjustment',NULL,'2026-03-14 19:56:39'),(61,3,1,23,18,-5,'Sale #INV-20260314-0014',1,'2026-03-14 19:56:39'),(62,3,1,18,13,-5,'Stock adjustment',NULL,'2026-03-14 19:57:17'),(63,3,1,18,13,-5,'Sale #INV-20260314-0015',1,'2026-03-14 19:57:17');
/*!40000 ALTER TABLE `inventory_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `inventory_status`
--

DROP TABLE IF EXISTS `inventory_status`;
/*!50001 DROP VIEW IF EXISTS `inventory_status`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `inventory_status` AS SELECT
 1 AS `id`,
  1 AS `product_name`,
  1 AS `sku`,
  1 AS `category_name`,
  1 AS `total_stock`,
  1 AS `branches_with_stock`,
  1 AS `min_stock`,
  1 AS `max_stock`,
  1 AS `selling_price`,
  1 AS `cost_price`,
  1 AS `profit_margin`,
  1 AS `stock_status` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `login_history`
--

DROP TABLE IF EXISTS `login_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_history`
--

LOCK TABLES `login_history` WRITE;
/*!40000 ALTER TABLE `login_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `low_stock_alerts`
--

DROP TABLE IF EXISTS `low_stock_alerts`;
/*!50001 DROP VIEW IF EXISTS `low_stock_alerts`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `low_stock_alerts` AS SELECT
 1 AS `product_id`,
  1 AS `product_name`,
  1 AS `sku`,
  1 AS `branch_id`,
  1 AS `branch_name`,
  1 AS `current_stock`,
  1 AS `reorder_level`,
  1 AS `needed_quantity` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `loyalty_points_log`
--

DROP TABLE IF EXISTS `loyalty_points_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `points_change` int(11) NOT NULL,
  `previous_balance` int(11) NOT NULL DEFAULT 0,
  `new_balance` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `loyalty_points_log_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loyalty_points_log_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_points_log`
--

LOCK TABLES `loyalty_points_log` WRITE;
/*!40000 ALTER TABLE `loyalty_points_log` DISABLE KEYS */;
INSERT INTO `loyalty_points_log` VALUES (1,2,350,0,350,'Active client','2027-03-12 22:09:26',1,'2026-03-13 00:09:26');
/*!40000 ALTER TABLE `loyalty_points_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_redemptions`
--

DROP TABLE IF EXISTS `loyalty_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_used` int(11) NOT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `redeemed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `reward_id` (`reward_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `loyalty_redemptions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loyalty_redemptions_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `loyalty_rewards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loyalty_redemptions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_redemptions`
--

LOCK TABLES `loyalty_redemptions` WRITE;
/*!40000 ALTER TABLE `loyalty_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_rewards`
--

DROP TABLE IF EXISTS `loyalty_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) NOT NULL,
  `reward_type` enum('discount','product','shipping') DEFAULT 'discount',
  `reward_value` decimal(10,2) DEFAULT 0.00,
  `valid_days` int(11) DEFAULT 30,
  `stock` int(11) DEFAULT 0,
  `stock_used` int(11) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `loyalty_rewards_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_rewards`
--

LOCK TABLES `loyalty_rewards` WRITE;
/*!40000 ALTER TABLE `loyalty_rewards` DISABLE KEYS */;
INSERT INTO `loyalty_rewards` VALUES (1,'Amazing','OFFERS',3000,'discount',5000.00,30,0,0,'',1,1,'2026-03-13 00:10:57',NULL);
/*!40000 ALTER TABLE `loyalty_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,1,'f8dd01bd0a434b4e76de3afed89a7fc6d0fd38e561b6888d73c3e54e44576eb3','2026-03-12 22:12:26',NULL,'2026-03-12 20:12:26'),(2,1,'e5d8db50b4a6518821b4125597bcb287f9de046934fd0fd1ee31b1f26bd106b5','2026-03-12 22:16:12',NULL,'2026-03-12 20:16:12'),(3,1,'bfc83067b0770dd342f5c0663b052aaab9266da4b774aeab956c40361269f09f','2026-03-12 22:18:09',NULL,'2026-03-12 20:18:09'),(4,1,'2006feaa8bd12fc43c5bcb1a54081e55c719769e766b53ac0e43ee8bfe1e66c1','2026-03-12 22:26:09',NULL,'2026-03-12 20:26:09');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `method` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'pos.view','Access POS screen'),(2,'pos.checkout','Process sales'),(3,'products.manage','Create/Update products'),(4,'inventory.manage','Adjust inventory'),(5,'reports.view','View reports'),(6,'customers.manage','Manage customers and loyalty'),(7,'suppliers.manage','Manage suppliers'),(8,'branches.manage','Manage branches'),(9,'purchase_orders.manage','Manage purchase orders'),(10,'discounts.manage','Manage discounts'),(11,'vouchers.manage','Manage vouchers'),(12,'notifications.view','View notifications'),(13,'activity.view','View activity logs'),(14,'backup.run','Run database backup'),(15,'restore.run','Restore database backup'),(16,'expenses.view','View expenses'),(17,'expenses.create','Create expenses'),(18,'expenses.manage','Manage expenses'),(19,'tax_rates.manage','Manage tax rates'),(20,'users.manage','Manage users');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sku` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(4) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `tax_rate_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  KEY `fk_product_tax_rate` (`tax_rate_id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `fk_product_tax_rate` FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_3` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_price_positive` CHECK (`price` >= 0),
  CONSTRAINT `check_cost_price_positive` CHECK (`cost_price` >= 0),
  CONSTRAINT `check_selling_price_ge_cost` CHECK (`selling_price` >= `cost_price` or `selling_price` is null)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'Bottled Water 1L','BW1',30.00,NULL,15.00,1,NULL,'2026-03-08 08:45:52',NULL,1,NULL,NULL,NULL,NULL),(2,1,'Soda 500ml','S500',50.00,NULL,25.00,1,NULL,'2026-03-08 08:45:52',NULL,1,NULL,NULL,NULL,NULL),(3,1,'Chips','CH01',100.00,NULL,60.00,1,'/uploads/product_images/p_69ad510d7ac09.png','2026-03-08 08:45:52',NULL,1,NULL,NULL,NULL,NULL),(4,1,'Toy Car','TJH 201',200.00,NULL,150.00,1,NULL,'2026-03-08 14:19:14',NULL,1,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER products_audit_insert AFTER INSERT ON products
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description, meta, created_at)
    VALUES (@current_user_id, 'product_create', 
            CONCAT('Created product: ', NEW.name),
            JSON_OBJECT('product_id', NEW.id, 'price', NEW.price, 'category_id', NEW.category_id),
            NOW());
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER products_audit_update AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
        INSERT INTO activity_logs (user_id, action, description, meta, created_at)
        VALUES (@current_user_id, 'product_delete', 
                CONCAT('Deleted product: ', NEW.name),
                JSON_OBJECT('product_id', NEW.id),
                NOW());
    ELSE
        INSERT INTO activity_logs (user_id, action, description, meta, created_at)
        VALUES (@current_user_id, 'product_update', 
                CONCAT('Updated product: ', NEW.name),
                JSON_OBJECT('product_id', NEW.id, 'old_price', OLD.price, 'new_price', NEW.price),
                NOW());
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `received_quantity` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
INSERT INTO `purchase_order_items` VALUES (1,1,1,2,0,100.00),(2,2,4,7,0,150.00);
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `expected_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
INSERT INTO `purchase_orders` VALUES (1,1,1,'received','2026-03-11','Well',NULL,'2026-03-08 21:00:51',NULL),(2,1,1,'received','2026-03-10','Test',NULL,'2026-03-08 21:18:30',NULL);
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_items`
--

DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurring_expenses`
--

DROP TABLE IF EXISTS `recurring_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recurring_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `frequency` enum('daily','weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
  `end_date` date DEFAULT NULL,
  `last_generated` date DEFAULT NULL,
  `next_generation` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  CONSTRAINT `recurring_expenses_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurring_expenses`
--

LOCK TABLES `recurring_expenses` WRITE;
/*!40000 ALTER TABLE `recurring_expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurring_expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_items`
--

DROP TABLE IF EXISTS `return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `return_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_items_return_fk` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_items`
--

LOCK TABLES `return_items` WRITE;
/*!40000 ALTER TABLE `return_items` DISABLE KEYS */;
INSERT INTO `return_items` VALUES (1,1,3,4,100.00,400.00),(2,2,3,4,100.00,400.00),(3,3,2,4,50.00,200.00);
/*!40000 ALTER TABLE `return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `returns`
--

DROP TABLE IF EXISTS `returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `processed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  KEY `branch_id` (`branch_id`),
  KEY `processed_by` (`processed_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_updated` (`updated_at`),
  CONSTRAINT `returns_approved_by_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `returns_branch_fk` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `returns_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `returns_processed_by_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `returns_sale_fk` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `returns`
--

LOCK TABLES `returns` WRITE;
/*!40000 ALTER TABLE `returns` DISABLE KEYS */;
INSERT INTO `returns` VALUES (1,'R20260314-716',38,NULL,1,1,NULL,'Wrong Item',400.00,'pending','','2026-03-14 22:44:38','2026-03-14 22:44:38'),(2,'R20260314-393',37,NULL,1,1,NULL,'Wrong Item',400.00,'pending','','2026-03-14 22:53:56','2026-03-14 22:53:56'),(3,'R20260315-201',32,NULL,1,1,NULL,'Quality Issue',200.00,'pending','','2026-03-14 23:06:04','2026-03-14 23:06:04');
/*!40000 ALTER TABLE `returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(3,1),(3,2),(3,3),(3,4),(3,5),(3,6),(3,7),(3,9),(3,12),(4,1),(4,2),(4,12),(5,3),(5,4),(5,9),(5,12),(6,5),(6,12),(6,14);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Admin',''),(2,'Admin',NULL),(3,'Manager',NULL),(4,'Cashier',NULL),(5,'Inventory Clerk',NULL),(6,'Accountant',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`,`sale_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,5,4,1,200.00,200.00),(2,6,2,1,50.00,50.00),(8,10,2,1,50.00,50.00),(9,11,2,1,50.00,50.00),(10,11,4,1,200.00,200.00),(11,12,2,1,50.00,50.00),(12,12,1,3,30.00,90.00),(13,13,4,1,200.00,200.00),(14,13,2,1,50.00,50.00),(15,14,4,1,200.00,200.00),(16,14,2,1,50.00,50.00),(17,14,3,5,100.00,500.00),(18,14,1,4,30.00,120.00),(19,15,2,1,50.00,50.00),(20,15,4,1,200.00,200.00),(21,16,2,1,50.00,50.00),(22,16,4,3,200.00,600.00),(23,16,1,4,30.00,120.00),(24,17,1,2,30.00,60.00),(25,17,3,1,100.00,100.00),(26,17,2,3,50.00,150.00),(27,17,4,1,200.00,200.00),(28,18,2,1,50.00,50.00),(29,18,4,1,200.00,200.00),(30,18,1,1,30.00,30.00),(31,19,2,1,50.00,50.00),(32,20,2,4,50.00,200.00),(33,20,1,1,30.03,30.03),(34,21,2,1,50.00,50.00),(35,21,4,1,200.00,200.00),(36,21,1,1,30.00,30.00),(37,22,2,1,50.00,50.00),(38,23,2,1,50.00,50.00),(39,24,2,1,50.00,50.00),(40,25,2,1,50.00,50.00),(41,26,2,1,50.00,50.00),(42,26,1,2,30.00,60.00),(43,27,1,1,30.00,30.00),(44,27,3,1,100.00,100.00),(45,28,2,2,50.00,100.00),(46,29,2,2,50.00,100.00),(47,30,1,2,30.00,60.00),(48,31,2,10,50.00,500.00),(49,32,2,4,50.00,200.00),(50,37,3,5,100.00,500.00),(51,38,3,5,100.00,500.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(20) DEFAULT 'fixed',
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_exempt` tinyint(1) DEFAULT 0,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(20) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pos_transaction` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'draft',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_sales_created_at` (`created_at`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_status_date` (`status`,`created_at`),
  KEY `idx_sales_branch_date` (`branch_id`,`created_at`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (5,NULL,1,1,NULL,200.00,232.00,'',0.00,'fixed',0.00,16.00,0,32.00,0.00,'',NULL,'2026-03-12 18:05:53',0,'draft',NULL),(6,NULL,1,1,NULL,50.00,58.00,'',0.00,'fixed',0.00,16.00,0,8.00,0.00,'',NULL,'2026-03-12 18:06:06',0,'draft',NULL),(10,'INV-20260313-6971',1,1,NULL,50.00,43.50,'',12.50,'discount',12.50,16.00,0,6.00,0.00,'cash','','2026-03-13 07:28:35',0,'completed','2026-03-13 10:28:35'),(11,'INV-20260313-9644',1,1,NULL,250.00,290.00,'well',0.00,'none',0.00,NULL,0,40.00,0.00,'cash','','2026-03-13 11:08:27',0,'completed','2026-03-13 14:08:27'),(12,'INV-20260313-6232',1,1,NULL,140.00,140.00,'Good',0.00,'none',0.00,0.00,0,22.40,0.00,'split','','2026-03-13 11:29:07',0,'completed','2026-03-13 14:29:07'),(13,'INV-20260313-4493',1,1,NULL,250.00,290.00,'',0.00,'none',0.00,0.00,0,40.00,0.00,'split','','2026-03-13 11:31:19',0,'completed','2026-03-13 14:31:19'),(14,'INV-20260313-7722',1,1,NULL,870.00,1009.20,'Paid',0.00,'none',0.00,0.00,0,139.20,0.00,'split','','2026-03-13 11:51:52',0,'completed','2026-03-13 14:51:52'),(15,'INV-20260313-3370',1,1,NULL,250.00,290.00,'Paid',0.00,'none',0.00,0.00,0,40.00,0.00,'cash','','2026-03-13 11:59:26',0,'completed','2026-03-13 14:59:26'),(16,'INV-20260313-0414',1,1,NULL,770.00,893.20,'Paid',0.00,'none',0.00,0.00,0,123.20,0.00,'split','','2026-03-13 13:42:18',0,'completed','2026-03-13 16:42:18'),(17,'INV-20260313-1380',1,1,NULL,510.00,591.60,'paid',0.00,'none',0.00,0.00,0,81.60,0.00,'cash','','2026-03-13 15:09:17',0,'completed','2026-03-13 18:09:17'),(18,'INV-20260313-9392',1,1,NULL,280.00,324.80,'',0.00,'none',0.00,0.00,0,44.80,0.00,'cash','','2026-03-13 18:29:00',0,'completed','2026-03-13 21:29:00'),(19,'INV-20260313-4404',1,1,NULL,50.00,58.00,'',0.00,'none',0.00,0.00,0,8.00,0.00,'cash','','2026-03-13 19:13:29',0,'completed','2026-03-13 22:13:29'),(20,'INV-20260314-3820',1,1,2,230.03,200.13,'',57.51,'discount',57.51,0.00,0,27.60,0.00,'cash','','2026-03-14 07:10:19',0,'completed','2026-03-14 10:10:19'),(21,'INV-20260314-2641',1,1,NULL,280.00,324.80,'',0.00,'none',0.00,0.00,0,44.80,0.00,'cash','','2026-03-14 14:53:33',0,'completed','2026-03-14 17:53:33'),(22,'INV-20260314-0003',1,1,NULL,50.00,58.00,'',0.00,'fixed',0.00,0.00,0,0.00,8.00,'cash','','2026-03-14 15:21:23',0,'completed',NULL),(23,'INV-20260314-0004',1,1,NULL,50.00,58.00,'',0.00,'fixed',0.00,0.00,0,0.00,8.00,'card','','2026-03-14 15:21:47',0,'completed',NULL),(24,'INV-20260314-0005',1,1,NULL,50.00,58.00,'',0.00,'fixed',0.00,0.00,0,0.00,8.00,'cash','','2026-03-14 15:22:32',0,'completed',NULL),(25,'INV-20260314-0006',1,1,NULL,50.00,58.00,'',0.00,'fixed',0.00,0.00,0,0.00,8.00,'mpesa','','2026-03-14 15:26:00',0,'completed',NULL),(26,'INV-20260314-0007',1,1,NULL,110.00,127.60,'',0.00,'fixed',0.00,0.00,0,0.00,17.60,'card','','2026-03-14 15:28:24',0,'completed',NULL),(27,'INV-20260314-0008',1,1,NULL,130.00,150.80,'',0.00,'fixed',0.00,0.00,0,0.00,20.80,'mpesa','','2026-03-14 15:28:57',0,'completed',NULL),(28,'INV-20260314-0009',1,1,NULL,100.00,116.00,'',0.00,'fixed',0.00,0.00,0,0.00,16.00,'mpesa','','2026-03-14 15:31:54',0,'completed',NULL),(29,'INV-20260314-0010',1,1,NULL,100.00,116.00,'',0.00,'fixed',0.00,0.00,0,0.00,16.00,'mpesa','','2026-03-14 15:38:26',0,'completed',NULL),(30,'INV-20260314-0011',1,1,NULL,60.00,69.60,'',0.00,'fixed',0.00,0.00,0,0.00,9.60,'cash','','2026-03-14 15:38:55',0,'completed',NULL),(31,'INV-20260314-0012',1,1,NULL,500.00,580.00,'',0.00,'fixed',0.00,0.00,0,0.00,80.00,'cash','','2026-03-14 15:41:17',0,'completed',NULL),(32,'INV-20260314-0013',1,1,NULL,200.00,232.00,'',0.00,'fixed',0.00,0.00,0,0.00,32.00,'cash','','2026-03-14 15:41:38',0,'completed',NULL),(37,'INV-20260314-0014',1,1,NULL,500.00,580.00,'',0.00,'fixed',0.00,0.00,0,0.00,80.00,'cash','','2026-03-14 19:56:39',0,'completed',NULL),(38,'INV-20260314-0015',1,1,NULL,500.00,580.00,'',0.00,'fixed',0.00,0.00,0,0.00,80.00,'mpesa','','2026-03-14 19:57:17',0,'completed',NULL);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `data_type` enum('text','number','boolean','json','email','url') DEFAULT 'text',
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'company_name','Jakababa POS',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','company',NULL,'text'),(2,'company_email','info@jakababa.com',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','company',NULL,'text'),(3,'company_phone','+254759714022',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','company',NULL,'text'),(4,'company_address','Nairobi, Kenya',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','company',NULL,'text'),(5,'tax_rate','16',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','tax',NULL,'number'),(6,'currency','USD',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','localization',NULL,'text'),(7,'timezone','Asia/Jakarta',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','localization',NULL,'text'),(8,'date_format','d M Y',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','localization',NULL,'text'),(9,'time_format','H:i',NULL,1,'2026-03-10 15:53:09','2026-03-12 16:55:47','localization',NULL,'text');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_number` varchar(100) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `courier` varchar(100) DEFAULT NULL,
  `courier_service` varchar(100) DEFAULT NULL,
  `status` enum('pending','shipped','delivered','cancelled') DEFAULT 'pending',
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `estimated_delivery` date DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_number` (`tracking_number`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tracking` (`tracking_number`),
  CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments`
--

LOCK TABLES `shipments` WRITE;
/*!40000 ALTER TABLE `shipments` DISABLE KEYS */;
/*!40000 ALTER TABLE `shipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `address` text DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'ABC Distributors','Mary','+254722222222','abc@suppliers.com',1,NULL,NULL,NULL,NULL,'2026-03-09 19:27:16',1,NULL,NULL,NULL),(2,'Shacaz Baby Shop','','+254115117676','',0,'400200\r\nKisii Teaching and Referral Hospital level 6 road','','',NULL,'2026-03-09 19:27:22',1,NULL,NULL,NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `type` enum('vat','gst','sales','income','withholding','excise','custom','other') DEFAULT 'vat',
  `description` text DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tax_rates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rates`
--

LOCK TABLES `tax_rates` WRITE;
/*!40000 ALTER TABLE `tax_rates` DISABLE KEYS */;
INSERT INTO `tax_rates` VALUES (1,'No Tax',0.00,0,1,NULL,'2026-03-12 16:55:15',NULL,'vat',NULL,NULL,NULL),(2,'VAT Standard',16.00,1,1,NULL,'2026-03-12 16:55:15',NULL,'vat',NULL,NULL,NULL),(3,'VAT Zero Rated',0.00,0,1,NULL,'2026-03-12 16:55:15',NULL,'vat',NULL,NULL,NULL),(4,'VAT Exempt',0.00,0,1,NULL,'2026-03-12 16:55:15',NULL,'vat',NULL,NULL,NULL),(5,'No Tax',0.00,0,1,NULL,'2026-03-12 23:10:02',NULL,'other',NULL,NULL,NULL),(6,'VAT Standard',16.00,1,1,NULL,'2026-03-12 23:10:02',NULL,'vat',NULL,NULL,NULL),(7,'VAT Zero Rated',0.00,0,1,NULL,'2026-03-12 23:10:02',NULL,'vat',NULL,NULL,NULL),(8,'VAT Exempt',0.00,0,1,NULL,'2026-03-12 23:10:02',NULL,'vat',NULL,NULL,NULL),(9,'No Tax',0.00,0,1,NULL,'2026-03-14 04:59:11',NULL,'other','No tax applicable','2026-03-14',NULL),(10,'VAT Standard',16.00,1,1,NULL,'2026-03-14 04:59:11',NULL,'vat','Standard VAT rate 16%','2026-03-14',NULL),(11,'VAT Zero Rated',0.00,0,1,NULL,'2026-03-14 04:59:11',NULL,'vat','Zero-rated supplies','2026-03-14',NULL),(12,'VAT Exempt',0.00,0,1,NULL,'2026-03-14 04:59:11',NULL,'vat','VAT exempt supplies','2026-03-14',NULL),(13,'VAT Standard',16.00,0,1,NULL,'2026-03-14 06:43:14',NULL,'',NULL,NULL,NULL),(14,'VAT Zero',0.00,0,1,NULL,'2026-03-14 06:43:14',NULL,'',NULL,NULL,NULL),(15,'Exempt',0.00,0,1,NULL,'2026-03-14 06:43:14',NULL,'',NULL,NULL,NULL),(16,'VAT Standard',16.00,0,1,NULL,'2026-03-14 06:44:56',NULL,'',NULL,NULL,NULL),(17,'VAT Zero',0.00,0,1,NULL,'2026-03-14 06:44:56',NULL,'',NULL,NULL,NULL),(18,'Exempt',0.00,0,1,NULL,'2026-03-14 06:44:56',NULL,'',NULL,NULL,NULL);
/*!40000 ALTER TABLE `tax_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `top_selling_products`
--

DROP TABLE IF EXISTS `top_selling_products`;
/*!50001 DROP VIEW IF EXISTS `top_selling_products`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `top_selling_products` AS SELECT
 1 AS `id`,
  1 AS `product_name`,
  1 AS `category_name`,
  1 AS `times_sold`,
  1 AS `total_quantity_sold`,
  1 AS `total_revenue`,
  1 AS `average_selling_price` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `user_branches`
--

DROP TABLE IF EXISTS `user_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `user_branches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_branches_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_branches`
--

LOCK TABLES `user_branches` WRITE;
/*!40000 ALTER TABLE `user_branches` DISABLE KEYS */;
INSERT INTO `user_branches` VALUES (1,1,2,'2026-03-12 21:37:55',NULL),(2,1,1,'2026-03-12 21:37:55',NULL),(3,1,1,'2026-03-14 04:56:55',NULL);
/*!40000 ALTER TABLE `user_branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1),(1,4),(2,4);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `branch_id` int(11) DEFAULT 1,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `last_login_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Wycliffe Bunde','wickymacochiz80@gmail.com','admin',NULL,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',0,1,'2026-03-08 08:45:52','+254759714022','',NULL,'2026-03-12 21:37:55',NULL,NULL,NULL,0,NULL,NULL,0,NULL),(2,'Cashier One',NULL,'cashier',NULL,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',1,1,'2026-03-08 08:45:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voucher_redemptions`
--

DROP TABLE IF EXISTS `voucher_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voucher_redemptions`
--

LOCK TABLES `voucher_redemptions` WRITE;
/*!40000 ALTER TABLE `voucher_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `voucher_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `type` enum('fixed','percent') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `active` tinyint(4) DEFAULT 1,
  `min_purchase` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
INSERT INTO `vouchers` VALUES (1,'WELCOME10','percent',10.00,'2026-12-31',1,0.00,NULL,100,'Welcome discount voucher',NULL,'2026-03-08 18:53:50'),(2,'FFDJGFTEFGVFDM,','percent',30.00,'2026-03-11',1,1000.00,2500.00,3,'Big',NULL,'2026-03-08 18:53:55');
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'jakababa_pos'
--

--
-- Dumping routines for database 'jakababa_pos'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_index_if_not_exists` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `create_index_if_not_exists`()
BEGIN
    -- Check and create idx_expenses_date if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'expenses' 
        AND index_name = 'idx_expenses_date'
    ) THEN
        CREATE INDEX idx_expenses_date ON expenses(expense_date);
    END IF;

    -- Check and create idx_expenses_status if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'expenses' 
        AND index_name = 'idx_expenses_status'
    ) THEN
        CREATE INDEX idx_expenses_status ON expenses(status);
    END IF;

    -- Check and create idx_sales_status_date if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'sales' 
        AND index_name = 'idx_sales_status_date'
    ) THEN
        CREATE INDEX idx_sales_status_date ON sales(status, created_at);
    END IF;

    -- Check and create idx_inventory_stock_level if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'inventory' 
        AND index_name = 'idx_inventory_stock_level'
    ) THEN
        CREATE INDEX idx_inventory_stock_level ON inventory(stock, reorder_level);
    END IF;

    -- Check and create idx_expenses_branch_date if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'expenses' 
        AND index_name = 'idx_expenses_branch_date'
    ) THEN
        CREATE INDEX idx_expenses_branch_date ON expenses(branch_id, expense_date);
    END IF;

    -- Check and create idx_sales_branch_date if not exists
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
        AND table_name = 'sales' 
        AND index_name = 'idx_sales_branch_date'
    ) THEN
        CREATE INDEX idx_sales_branch_date ON sales(branch_id, created_at);
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `branch_customers`
--

/*!50001 DROP VIEW IF EXISTS `branch_customers`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `branch_customers` AS select distinct `c`.`id` AS `id`,`c`.`name` AS `name`,`c`.`phone` AS `phone`,`c`.`email` AS `email`,`c`.`loyalty_points` AS `loyalty_points`,`c`.`active` AS `active`,`c`.`created_at` AS `created_at`,`c`.`updated_at` AS `updated_at`,`c`.`status` AS `status`,`c`.`deleted_at` AS `deleted_at`,`c`.`deleted_by` AS `deleted_by`,`c`.`credit_limit` AS `credit_limit`,`c`.`loyalty_tier` AS `loyalty_tier`,`c`.`total_spent` AS `total_spent`,`c`.`last_purchase` AS `last_purchase`,`s`.`branch_id` AS `branch_id` from (`customers` `c` join `sales` `s` on(`c`.`id` = `s`.`customer_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `branch_products`
--

/*!50001 DROP VIEW IF EXISTS `branch_products`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `branch_products` AS select distinct `p`.`id` AS `id`,`p`.`category_id` AS `category_id`,`p`.`name` AS `name`,`p`.`sku` AS `sku`,`p`.`price` AS `price`,`p`.`selling_price` AS `selling_price`,`p`.`cost_price` AS `cost_price`,`p`.`active` AS `active`,`p`.`image` AS `image`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`p`.`status` AS `status`,`p`.`tax_rate_id` AS `tax_rate_id`,`p`.`deleted_at` AS `deleted_at`,`p`.`deleted_by` AS `deleted_by`,`i`.`branch_id` AS `branch_id` from (`products` `p` join `inventory` `i` on(`p`.`id` = `i`.`product_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `branch_suppliers`
--

/*!50001 DROP VIEW IF EXISTS `branch_suppliers`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `branch_suppliers` AS select distinct `s`.`id` AS `id`,`s`.`name` AS `name`,`s`.`contact` AS `contact`,`s`.`phone` AS `phone`,`s`.`email` AS `email`,`s`.`active` AS `active`,`s`.`address` AS `address`,`s`.`tax_id` AS `tax_id`,`s`.`payment_terms` AS `payment_terms`,`s`.`created_at` AS `created_at`,`s`.`status` AS `status`,`s`.`updated_at` AS `updated_at`,`s`.`deleted_at` AS `deleted_at`,`s`.`deleted_by` AS `deleted_by`,`po`.`branch_id` AS `branch_id` from (`suppliers` `s` join `purchase_orders` `po` on(`s`.`id` = `po`.`supplier_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `customer_loyalty`
--

/*!50001 DROP VIEW IF EXISTS `customer_loyalty`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `customer_loyalty` AS select `c`.`id` AS `id`,`c`.`name` AS `customer_name`,`c`.`phone` AS `phone`,`c`.`email` AS `email`,`c`.`loyalty_points` AS `loyalty_points`,count(distinct `s`.`id`) AS `total_transactions`,coalesce(sum(`s`.`total`),0) AS `total_spent`,coalesce(avg(`s`.`total`),0) AS `average_transaction_value`,max(`s`.`created_at`) AS `last_purchase_date` from (`customers` `c` left join `sales` `s` on(`c`.`id` = `s`.`customer_id` and `s`.`status` = 'completed')) group by `c`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `daily_sales_summary`
--

/*!50001 DROP VIEW IF EXISTS `daily_sales_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `daily_sales_summary` AS select cast(`s`.`created_at` as date) AS `sale_date`,`b`.`name` AS `branch_name`,`b`.`id` AS `branch_id`,count(distinct `s`.`id`) AS `transaction_count`,coalesce(sum(`s`.`total`),0) AS `total_sales`,coalesce(sum(`s`.`discount`),0) AS `total_discounts`,coalesce(avg(`s`.`total`),0) AS `average_transaction_value`,count(distinct `s`.`customer_id`) AS `unique_customers` from (`branches` `b` left join `sales` `s` on(`b`.`id` = `s`.`branch_id` and `s`.`status` = 'completed')) group by cast(`s`.`created_at` as date),`b`.`id`,`b`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `financial_summary`
--

/*!50001 DROP VIEW IF EXISTS `financial_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `financial_summary` AS select cast(`s`.`created_at` as date) AS `date`,'sales' AS `type`,sum(`s`.`total`) AS `amount`,count(distinct `s`.`id`) AS `count` from `sales` `s` where `s`.`status` = 'completed' group by cast(`s`.`created_at` as date) union all select cast(`e`.`expense_date` as date) AS `date`,'expense' AS `type`,sum(`e`.`amount`) AS `amount`,count(distinct `e`.`id`) AS `count` from `expenses` `e` where `e`.`status` = 'approved' group by cast(`e`.`expense_date` as date) order by `date` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `inventory_status`
--

/*!50001 DROP VIEW IF EXISTS `inventory_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `inventory_status` AS select `p`.`id` AS `id`,`p`.`name` AS `product_name`,`p`.`sku` AS `sku`,`c`.`name` AS `category_name`,coalesce(sum(`i`.`stock`),0) AS `total_stock`,count(distinct `i`.`branch_id`) AS `branches_with_stock`,min(`i`.`stock`) AS `min_stock`,max(`i`.`stock`) AS `max_stock`,`p`.`price` AS `selling_price`,`p`.`cost_price` AS `cost_price`,`p`.`price` - `p`.`cost_price` AS `profit_margin`,case when coalesce(sum(`i`.`stock`),0) <= 0 then 'Out of Stock' when coalesce(sum(`i`.`stock`),0) <= `i`.`minimum_stock` then 'Low Stock' else 'In Stock' end AS `stock_status` from ((`products` `p` left join `categories` `c` on(`p`.`category_id` = `c`.`id`)) left join `inventory` `i` on(`p`.`id` = `i`.`product_id`)) where `p`.`deleted_at` is null group by `p`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `low_stock_alerts`
--

/*!50001 DROP VIEW IF EXISTS `low_stock_alerts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `low_stock_alerts` AS select `p`.`id` AS `product_id`,`p`.`name` AS `product_name`,`p`.`sku` AS `sku`,`i`.`branch_id` AS `branch_id`,`b`.`name` AS `branch_name`,`i`.`stock` AS `current_stock`,`i`.`reorder_level` AS `reorder_level`,`i`.`reorder_level` - `i`.`stock` AS `needed_quantity` from ((`inventory` `i` join `products` `p` on(`i`.`product_id` = `p`.`id`)) join `branches` `b` on(`i`.`branch_id` = `b`.`id`)) where `i`.`stock` <= `i`.`reorder_level` and `i`.`stock` > 0 order by `i`.`stock` / `i`.`reorder_level` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `top_selling_products`
--

/*!50001 DROP VIEW IF EXISTS `top_selling_products`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `top_selling_products` AS select `p`.`id` AS `id`,`p`.`name` AS `product_name`,`c`.`name` AS `category_name`,count(`si`.`id`) AS `times_sold`,sum(`si`.`quantity`) AS `total_quantity_sold`,sum(`si`.`price` * `si`.`quantity`) AS `total_revenue`,avg(`si`.`price`) AS `average_selling_price` from (((`products` `p` left join `categories` `c` on(`p`.`category_id` = `c`.`id`)) left join `sale_items` `si` on(`p`.`id` = `si`.`product_id`)) left join `sales` `s` on(`si`.`sale_id` = `s`.`id`)) where `s`.`status` = 'completed' group by `p`.`id` order by sum(`si`.`quantity`) desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-15  2:23:27
