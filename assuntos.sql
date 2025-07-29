-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 29/07/2025 às 23:18
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
(5, 1, 'Direitos e Garantias Fundamentais', NULL, '2025-07-09', NULL);

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
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `assuntos`
--
ALTER TABLE `assuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `assuntos`
--
ALTER TABLE `assuntos`
  ADD CONSTRAINT `assuntos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
