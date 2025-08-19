-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 19/08/2025 às 18:13
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `sistema_revisao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `assuntos`
--

CREATE TABLE `assuntos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `criado_em` date NOT NULL DEFAULT curdate(),
  `tags` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `assuntos`
--

INSERT INTO `assuntos` (`id`, `user_id`, `titulo`, `descricao`, `criado_em`, `tags`) VALUES
(1, 1, 'Controle de Constitucionalidade', NULL, '2025-07-19', NULL),
(2, 1, 'Crase', NULL, '2025-07-24', NULL),
(3, 1, 'Atos Administrativos', NULL, '2025-07-29', NULL),
(4, 1, 'Segurança da Informação', NULL, '2025-07-31', NULL),
(5, 1, 'Direitos e Garantias Fundamentais', NULL, '2025-07-09', NULL),
(6, 1, 'Tipos de Memória', NULL, '2025-08-11', NULL),
(22, 21, 'Uso da Vírgula', NULL, '2025-08-05', NULL),
(23, 21, 'Tabela Verdade', NULL, '2025-08-10', NULL),
(24, 21, 'Estrutura da Redação Dissertativa', NULL, '2025-08-12', NULL),
(25, 21, 'Teoria dos Conjuntos', NULL, '2025-08-18', NULL),
(30, 21, 'Argumentação e Coerência', NULL, '2025-08-05', NULL),
(31, 21, 'Proposições e Conectivos', NULL, '2025-08-10', NULL),
(32, 21, 'Figuras de Linguagem', NULL, '2025-08-12', NULL),
(33, 21, 'Análise Combinatória', NULL, '2025-08-18', NULL),
(78, 22, 'Concordância Verbal', 'Regras de concordância do verbo com o sujeito.', '2025-08-19', 'Português'),
(79, 22, 'Funções do 1º e 2º Grau', 'Estudo das funções polinomiais básicas.', '2025-08-19', 'Matemática'),
(80, 22, 'Redes de Computadores', 'Modelo OSI e TCP/IP.', '2025-08-19', 'Informática'),
(81, 22, 'Controle Difuso e Concentrado', 'Modalidades do controle de constitucionalidade.', '2025-08-19', 'Direito Constitucional'),
(82, 22, 'Interpretação de Texto', 'Estratégias para interpretar textos em provas.', '2025-08-10', 'Português'),
(83, 22, 'Probabilidade Básica', 'Conceitos iniciais de probabilidade.', '2025-08-15', 'Matemática');

-- --------------------------------------------------------

--
-- Estrutura para tabela `assunto_tag`
--

CREATE TABLE `assunto_tag` (
  `id` int(11) NOT NULL,
  `assunto_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `assunto_tag`
--

INSERT INTO `assunto_tag` (`id`, `assunto_id`, `tag_id`) VALUES
(1, 1, 1),
(3, 3, 3),
(4, 4, 4),
(5, 5, 1),
(6, 6, 4),
(21, 22, 14),
(24, 25, 11),
(32, 32, 14),
(33, 33, 11),
(76, 78, 14),
(77, 79, 11),
(78, 80, 4),
(79, 81, 1),
(80, 82, 14),
(81, 83, 11);

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expira` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expira`) VALUES
(1, 1, 'dfbff1b6d2f37538ff13e1953ab69ca219001f8e0f457b375c453c3aefd1ff80edb46398f50288ea0731b88912b931cb7c47', '2025-08-17 14:49:52'),
(2, 1, 'b3dd9410b0aa5773f042784b614c3615fc512e923ff60470fea4b37f6cc6bc058628ba4647c10bbd7bfa0c4989289a2ebe7b', '2025-08-17 14:55:03'),
(3, 1, 'e73bcd83b3e5ea738ae1a6bd9a02ad119a8163d3a05b77af7ef7cab0141fe5b1d77028e3cf8b905c4b8612a10621a733727f', '2025-08-17 14:55:07'),
(4, 1, 'e81209336f8d89073dec5f834f8f39ec7a921bcca57c79d4378b8ed2278e4c8d577f01f9d849a9ed1d0ae8770ccd5081ad5d', '2025-08-17 14:55:19'),
(5, 1, 'aad68465f6350e68544ca7810219001a19ada6762507c117055536df1d4d3e35a752e74974bef776723252c08b6a53c6f03f', '2025-08-17 14:59:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `revisoes`
--

CREATE TABLE `revisoes` (
  `id` int(11) NOT NULL,
  `assunto_id` int(11) NOT NULL,
  `dia_revisao` int(11) NOT NULL,
  `data_revisao` date NOT NULL,
  `feita` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `revisoes`
--

INSERT INTO `revisoes` (`id`, `assunto_id`, `dia_revisao`, `data_revisao`, `feita`) VALUES
(1, 1, 0, '2025-07-19', 1),
(2, 1, 1, '2025-07-20', 1),
(3, 1, 7, '2025-07-26', 0),
(4, 1, 14, '2025-08-02', 0),
(5, 1, 30, '2025-08-18', 0),
(6, 1, 60, '2025-09-17', 0),
(7, 1, 120, '2025-11-16', 0),
(8, 2, 0, '2025-07-24', 1),
(9, 2, 1, '2025-07-25', 0),
(10, 2, 7, '2025-07-31', 0),
(11, 2, 14, '2025-08-07', 0),
(12, 2, 30, '2025-08-23', 0),
(13, 2, 60, '2025-09-22', 0),
(14, 2, 120, '2025-11-21', 0),
(15, 3, 0, '2025-07-29', 0),
(16, 3, 1, '2025-07-30', 0),
(17, 3, 7, '2025-08-05', 0),
(18, 3, 14, '2025-08-12', 0),
(19, 3, 30, '2025-08-28', 0),
(20, 3, 60, '2025-09-27', 0),
(21, 3, 120, '2025-11-26', 0),
(22, 4, 0, '2025-07-31', 0),
(23, 4, 1, '2025-08-01', 0),
(24, 4, 7, '2025-08-07', 0),
(25, 4, 14, '2025-08-14', 0),
(26, 4, 30, '2025-08-30', 0),
(27, 4, 60, '2025-09-29', 0),
(28, 4, 120, '2025-11-28', 0),
(29, 5, 0, '2025-07-09', 1),
(30, 5, 1, '2025-07-10', 1),
(31, 5, 7, '2025-07-16', 1),
(32, 5, 14, '2025-07-23', 0),
(33, 5, 30, '2025-08-08', 0),
(34, 5, 60, '2025-09-07', 0),
(35, 5, 120, '2025-11-06', 0),
(36, 6, 0, '2025-08-11', 0),
(37, 6, 1, '2025-08-12', 0),
(38, 6, 7, '2025-08-18', 0),
(39, 6, 14, '2025-08-25', 0),
(40, 6, 30, '2025-09-10', 0),
(41, 6, 60, '2025-10-10', 0),
(42, 6, 120, '2025-12-09', 0),
(176, 22, 0, '2025-08-05', 1),
(177, 22, 1, '2025-08-06', 1),
(178, 22, 7, '2025-08-12', 0),
(179, 22, 14, '2025-08-19', 0),
(180, 22, 30, '2025-09-04', 0),
(181, 22, 60, '2025-10-04', 0),
(182, 22, 120, '2025-12-03', 0),
(183, 23, 0, '2025-08-10', 1),
(184, 23, 1, '2025-08-11', 0),
(185, 23, 7, '2025-08-17', 0),
(186, 23, 14, '2025-08-24', 0),
(187, 23, 30, '2025-09-09', 0),
(188, 23, 60, '2025-10-09', 0),
(189, 23, 120, '2025-12-08', 0),
(190, 24, 0, '2025-08-12', 1),
(191, 24, 1, '2025-08-13', 1),
(192, 24, 7, '2025-08-19', 0),
(193, 24, 14, '2025-08-26', 0),
(194, 24, 30, '2025-09-11', 0),
(195, 24, 60, '2025-10-11', 0),
(196, 24, 120, '2025-12-10', 0),
(197, 25, 0, '2025-08-18', 1),
(198, 25, 1, '2025-08-19', 0),
(199, 25, 7, '2025-08-25', 0),
(200, 25, 14, '2025-09-01', 0),
(201, 25, 30, '2025-09-17', 0),
(202, 25, 60, '2025-10-17', 0),
(203, 25, 120, '2025-12-16', 0),
(250, 30, 0, '2025-08-05', 1),
(251, 30, 1, '2025-08-06', 1),
(252, 30, 7, '2025-08-12', 0),
(253, 30, 14, '2025-08-19', 0),
(254, 30, 30, '2025-09-04', 0),
(255, 30, 60, '2025-10-04', 0),
(256, 30, 120, '2025-12-03', 0),
(257, 31, 0, '2025-08-10', 1),
(258, 31, 1, '2025-08-11', 0),
(259, 31, 7, '2025-08-17', 0),
(260, 31, 14, '2025-08-24', 0),
(261, 31, 30, '2025-09-09', 0),
(262, 31, 60, '2025-10-09', 0),
(263, 31, 120, '2025-12-08', 0),
(264, 32, 0, '2025-08-12', 1),
(265, 32, 1, '2025-08-13', 1),
(266, 32, 7, '2025-08-19', 0),
(267, 32, 14, '2025-08-26', 0),
(268, 32, 30, '2025-09-11', 0),
(269, 32, 60, '2025-10-11', 0),
(270, 32, 120, '2025-12-10', 0),
(271, 33, 0, '2025-08-18', 1),
(272, 33, 1, '2025-08-19', 0),
(273, 33, 7, '2025-08-25', 0),
(274, 33, 14, '2025-09-01', 0),
(275, 33, 30, '2025-09-17', 0),
(276, 33, 60, '2025-10-17', 0),
(277, 33, 120, '2025-12-16', 0),
(821, 78, 0, '2025-08-19', 0),
(822, 78, 1, '2025-08-20', 0),
(823, 78, 7, '2025-08-26', 0),
(824, 78, 14, '2025-09-02', 0),
(825, 78, 30, '2025-09-18', 0),
(826, 78, 60, '2025-10-18', 0),
(827, 78, 120, '2025-12-17', 0),
(828, 79, 0, '2025-08-19', 0),
(829, 79, 1, '2025-08-20', 0),
(830, 79, 7, '2025-08-26', 0),
(831, 79, 14, '2025-09-02', 0),
(832, 79, 30, '2025-09-18', 0),
(833, 79, 60, '2025-10-18', 0),
(834, 79, 120, '2025-12-17', 0),
(835, 80, 0, '2025-08-19', 0),
(836, 80, 1, '2025-08-20', 0),
(837, 80, 7, '2025-08-26', 0),
(838, 80, 14, '2025-09-02', 0),
(839, 80, 30, '2025-09-18', 0),
(840, 80, 60, '2025-10-18', 0),
(841, 80, 120, '2025-12-17', 0),
(842, 81, 0, '2025-08-19', 0),
(843, 81, 1, '2025-08-20', 0),
(844, 81, 7, '2025-08-26', 0),
(845, 81, 14, '2025-09-02', 0),
(846, 81, 30, '2025-09-18', 0),
(847, 81, 60, '2025-10-18', 0),
(848, 81, 120, '2025-12-17', 0),
(849, 82, 0, '2025-08-10', 1),
(850, 82, 1, '2025-08-11', 1),
(851, 82, 7, '2025-08-17', 0),
(852, 82, 14, '2025-08-24', 0),
(853, 82, 30, '2025-09-09', 0),
(854, 82, 60, '2025-10-09', 0),
(855, 82, 120, '2025-12-08', 0),
(856, 83, 0, '2025-08-15', 1),
(857, 83, 1, '2025-08-16', 1),
(858, 83, 7, '2025-08-22', 0),
(859, 83, 14, '2025-08-29', 0),
(860, 83, 30, '2025-09-14', 0),
(861, 83, 60, '2025-10-14', 0),
(862, 83, 120, '2025-12-13', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tags`
--

INSERT INTO `tags` (`id`, `nome`) VALUES
(3, 'Direito Administrativo'),
(1, 'Direito Constitucional'),
(4, 'Informática'),
(11, 'Matemática'),
(14, 'Português');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 0,
  `token_verificacao` varchar(255) DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `nome`, `email`, `senha_hash`, `criado_em`, `ativo`, `token_verificacao`, `data_criacao`) VALUES
(1, 'Testa', 'teste@teste.com', '$2y$10$ktMXWNvteRpoRkLmv294LO8F5KORYlljd6YU450M4gbw4bHzzKJZ.', '2025-07-15 02:57:58', 0, NULL, '2025-08-17 10:31:14'),
(5, 'vana', 'vana@vana', '$2y$10$Pz.32EXXoR3zXXT4mbTt4.qElSC3rYrrcjQN4QyGaB/dlMeejllLu', '2025-07-15 21:11:57', 0, NULL, '2025-08-17 10:31:14'),
(21, 'Carlos Mendes', 'carlos.mendes@example.com', '$2y$10$IcaL8y2et1gqO822pceSaujJ1s2OOAlxnzecqBUSjAMYd44aLsIq.', '2025-08-19 03:00:00', 1, NULL, '2025-08-19 00:00:00'),
(22, 'Frederico Pellegrini', 'fredericopellegrini@proton.me', '$2y$10$vlY9H3iuHOrh0gVFg3ZHwe7ANIhE1nwqkRYi8/dlX/PGOC8kQBssC', '2025-08-17 14:26:17', 1, NULL, '2025-08-17 11:26:17');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `assuntos`
--
ALTER TABLE `assuntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `assunto_tag`
--
ALTER TABLE `assunto_tag`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assunto_id` (`assunto_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Índices de tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `revisoes`
--
ALTER TABLE `revisoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assunto_id` (`assunto_id`,`dia_revisao`);

--
-- Índices de tabela `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `assuntos`
--
ALTER TABLE `assuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT de tabela `assunto_tag`
--
ALTER TABLE `assunto_tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `revisoes`
--
ALTER TABLE `revisoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=947;

--
-- AUTO_INCREMENT de tabela `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `assuntos`
--
ALTER TABLE `assuntos`
  ADD CONSTRAINT `assuntos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `assunto_tag`
--
ALTER TABLE `assunto_tag`
  ADD CONSTRAINT `assunto_tag_ibfk_1` FOREIGN KEY (`assunto_id`) REFERENCES `assuntos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assunto_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `revisoes`
--
ALTER TABLE `revisoes`
  ADD CONSTRAINT `revisoes_ibfk_1` FOREIGN KEY (`assunto_id`) REFERENCES `assuntos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
