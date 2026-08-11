-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: task_management_v1
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `task_template_id` bigint unsigned DEFAULT NULL,
  `period_key` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_task_id` bigint unsigned DEFAULT NULL,
  `task_status_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `description_plain` longtext COLLATE utf8mb4_unicode_ci,
  `task_type` enum('daily','weekly','monthly','tentative','project') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `priority_quadrant` enum('p1','p2','p3','p4') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` smallint NOT NULL DEFAULT '0',
  `estimated_minutes` smallint NOT NULL,
  `actual_minutes` smallint DEFAULT NULL,
  `quality_rating` tinyint DEFAULT NULL,
  `rejection_count` smallint NOT NULL DEFAULT '0',
  `due_date` datetime NOT NULL,
  `original_due_date` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_template_period_unique` (`task_template_id`,`period_key`),
  KEY `tasks_project_id_foreign` (`project_id`),
  KEY `tasks_approved_by_foreign` (`approved_by`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_organization_id_project_id_index` (`organization_id`,`project_id`),
  KEY `tasks_task_status_id_index` (`task_status_id`),
  KEY `tasks_due_date_index` (`due_date`),
  KEY `tasks_parent_task_id_index` (`parent_task_id`),
  KEY `tasks_organization_id_due_date_task_status_id_index` (`organization_id`,`due_date`,`task_status_id`),
  KEY `tasks_task_template_id_index` (`task_template_id`),
  FULLTEXT KEY `fulltext_index` (`title`,`description_plain`),
  CONSTRAINT `tasks_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `tasks_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `tasks_parent_task_id_foreign` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`),
  CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `tasks_task_status_id_foreign` FOREIGN KEY (`task_status_id`) REFERENCES `task_statuses` (`id`),
  CONSTRAINT `tasks_task_template_id_foreign` FOREIGN KEY (`task_template_id`) REFERENCES `task_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,1,1,NULL,NULL,NULL,3,'hesoyam','<ol><li><p><strong><em>pppp</em></strong></p></li></ol><p></p>','pppp','tentative','normal',NULL,2,5,NULL,NULL,0,'2026-08-03 15:00:00',NULL,NULL,NULL,NULL,NULL,0,2,'2026-08-03 07:47:12','2026-08-03 07:50:34',NULL),(2,1,2,NULL,NULL,NULL,6,'coba coba','<p>qdwdwew</p>','qdwdwew','tentative','normal','p1',24,60,NULL,NULL,0,'2026-08-12 09:00:00',NULL,NULL,NULL,NULL,NULL,0,2,'2026-08-05 09:00:52','2026-08-05 09:11:30','2026-08-05 09:11:30'),(3,1,2,NULL,NULL,NULL,8,'cobaaaaaa','<p>edwfdewdwef</p>','edwfdewdwef','tentative','normal','p1',3,60,0,4,0,'2026-08-12 09:02:00',NULL,NULL,'2026-08-05 16:11:14','2026-08-05 16:11:14',2,0,2,'2026-08-05 09:02:57','2026-08-05 09:11:33','2026-08-05 09:11:33'),(4,1,2,NULL,NULL,NULL,7,'Tambal Ban','<p><strong>Lorem Ipsum</strong> is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum</p>','Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum','tentative','normal','p3',20,60,0,1,0,'2026-08-12 09:11:00',NULL,NULL,NULL,'2026-08-05 16:16:09',2,0,2,'2026-08-05 09:12:24','2026-08-06 06:43:09',NULL),(5,1,2,NULL,NULL,NULL,5,'H7 Browser Verify Task',NULL,NULL,'tentative','normal','p1',0,60,NULL,NULL,0,'2026-08-12 19:46:00',NULL,NULL,NULL,NULL,NULL,0,3,'2026-08-05 12:46:59','2026-08-06 01:57:02',NULL),(6,1,2,NULL,NULL,NULL,8,'Workkkkkk','<p><strong>Lorem Ipsum</strong> is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum</p>','Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum','tentative','normal','p2',80,120,0,4,0,'2026-08-07 06:39:00',NULL,NULL,'2026-08-06 13:45:33','2026-08-06 13:45:33',2,0,2,'2026-08-06 06:41:07','2026-08-06 06:45:33',NULL),(7,1,2,NULL,NULL,NULL,8,'Tugas untuk Ariel','<p>vsvsvdsvsd</p>','vsvsvdsvsd','tentative','normal','p3',30,60,0,4,0,'2026-08-13 08:29:00',NULL,NULL,'2026-08-06 15:33:53','2026-08-06 15:33:53',2,0,2,'2026-08-06 08:30:17','2026-08-06 08:33:53',NULL),(8,1,3,NULL,NULL,NULL,12,'Taskkkkkkkkkkk','<p>reregregregergegegegegreeer</p>','reregregregergegegegegreeer','project','normal','p4',3,60,0,5,0,'2026-08-13 08:39:00',NULL,NULL,'2026-08-07 08:58:11','2026-08-07 08:58:11',2,0,2,'2026-08-06 08:40:48','2026-08-07 01:58:11',NULL),(9,1,2,1,'2026-08-07',NULL,5,'Work From Home','<p><strong>Lorem Ipsum</strong> is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum</p>','Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum','daily','normal',NULL,20,60,NULL,NULL,0,'2026-08-09 23:59:00',NULL,NULL,NULL,NULL,NULL,0,2,'2026-08-07 03:03:33','2026-08-07 03:03:33',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_templates`
--

DROP TABLE IF EXISTS `task_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `task_type` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_minutes` smallint NOT NULL,
  `points` smallint NOT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `recurrence_config` json NOT NULL,
  `due_offset_days` smallint unsigned DEFAULT NULL,
  `default_assignees` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `anchor_strategy` enum('time_based','completion_based','calendar_anchored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'time_based',
  `interval_value` int unsigned DEFAULT NULL,
  `interval_unit` enum('day','week','month') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anchor_config` json DEFAULT NULL,
  `date_window_config` json DEFAULT NULL,
  `max_active_instances` int unsigned DEFAULT NULL,
  `blocked_since` date DEFAULT NULL,
  `last_block_notified_at` datetime DEFAULT NULL,
  `last_generated_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_templates_organization_id_foreign` (`organization_id`),
  KEY `task_templates_project_id_foreign` (`project_id`),
  CONSTRAINT `task_templates_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `task_templates_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_templates`
--

LOCK TABLES `task_templates` WRITE;
/*!40000 ALTER TABLE `task_templates` DISABLE KEYS */;
INSERT INTO `task_templates` VALUES (1,1,2,'Work From Home','<p><strong>Lorem Ipsum</strong> is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum</p>','daily',60,20,'normal','[]',2,'[11, 3]',1,'time_based',2,'day',NULL,'[]',NULL,NULL,NULL,'2026-08-07','2026-08-06 06:29:08','2026-08-07 03:04:45'),(2,1,3,'gggggggggggg','<p>gsdgggeg</p>','monthly',60,28,'urgent','{\"day_of_month\": 1}',1,'[3, 11]',1,'time_based',2,'month',NULL,'[]',NULL,NULL,NULL,NULL,'2026-08-07 03:06:58','2026-08-07 03:08:11');
/*!40000 ALTER TABLE `task_templates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-07 10:24:20
