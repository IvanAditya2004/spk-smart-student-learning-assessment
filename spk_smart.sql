-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2026 at 09:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `spk_smart`
--

-- --------------------------------------------------------

--
-- Table structure for table `kriteria`
--

CREATE TABLE `kriteria` (
  `id` int(11) NOT NULL,
  `kode` varchar(5) DEFAULT NULL,
  `nama_kriteria` varchar(100) DEFAULT NULL,
  `bobot` decimal(3,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kriteria`
--

INSERT INTO `kriteria` (`id`, `kode`, `nama_kriteria`, `bobot`) VALUES
(1, 'C1', 'Kognitif', 0.30),
(2, 'C2', 'Psikomotor', 0.25),
(3, 'C3', 'Afektif', 0.15),
(4, 'C4', 'Akhlak', 0.15),
(5, 'C5', 'Kehadiran', 0.15);

-- --------------------------------------------------------

--
-- Table structure for table `nilai`
--

CREATE TABLE `nilai` (
  `id` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `kode_kriteria` varchar(5) DEFAULT NULL,
  `nilai` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nilai`
--

INSERT INTO `nilai` (`id`, `id_siswa`, `kode_kriteria`, `nilai`) VALUES
(1, 3, 'C1', 87),
(2, 3, 'C2', 90),
(3, 3, 'C3', 100),
(4, 3, 'C4', 87),
(5, 3, 'C5', 70),
(6, 6, 'C1', 78),
(7, 6, 'C2', 87),
(8, 6, 'C3', 90),
(9, 6, 'C4', 99),
(10, 6, 'C5', 100),
(11, 5, 'C1', 90),
(12, 5, 'C2', 99),
(13, 5, 'C3', 78),
(14, 5, 'C4', 90),
(15, 5, 'C5', 80),
(16, 4, 'C1', 90),
(17, 4, 'C2', 90),
(18, 4, 'C3', 90),
(19, 4, 'C4', 99),
(20, 4, 'C5', 85),
(21, 7, 'C1', 85),
(22, 7, 'C2', 88),
(23, 7, 'C3', 80),
(24, 7, 'C4', 90),
(25, 7, 'C5', 95),
(26, 28, 'C1', 85),
(27, 28, 'C2', 88),
(28, 28, 'C3', 80),
(29, 28, 'C4', 90),
(30, 28, 'C5', 95),
(31, 29, 'C1', 90),
(32, 29, 'C2', 84),
(33, 29, 'C3', 85),
(34, 29, 'C4', 88),
(35, 29, 'C5', 92),
(36, 30, 'C1', 78),
(37, 30, 'C2', 82),
(38, 30, 'C3', 80),
(39, 30, 'C4', 85),
(40, 30, 'C5', 90);

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id_siswa` int(11) NOT NULL,
  `nis` varchar(20) DEFAULT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `kelas` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id_siswa`, `nis`, `nama_siswa`, `kelas`) VALUES
(28, '24001', 'Ahmad Fauzi', '8A'),
(29, '24002', 'Dinda Ayu Lestari', '8A'),
(30, '24003', 'Rizky Ramadhan', '8A'),
(31, '24004', 'Siti Aisyah', '8A'),
(32, '24005', 'Muhammad Fajar', '8A'),
(33, '24006', 'Nabila Putri', '8B'),
(34, '24007', 'Alif Pratama', '8B'),
(35, '24008', 'Salsa Anindya', '8B'),
(36, '24009', 'Bagas Saputra', '8B'),
(37, '24010', 'Rina Oktavia', '8B'),
(38, '24011', 'Farhan Maulana', '8C'),
(39, '24012', 'Zahra Khairunnisa', '8C'),
(40, '24013', 'Fikri Hidayat', '8C'),
(41, '24014', 'Putri Maharani', '8C'),
(42, '24015', 'Yoga Prasetyo', '8C'),
(43, '24016', 'Intan Permata', '8D'),
(44, '24017', 'Reza Firmansyah', '8D'),
(45, '24018', 'Aulia Rahma', '8D'),
(46, '24019', 'Dimas Saputra', '8D'),
(47, '24020', 'Citra Lestari', '8D');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$y7voSaRZHUq6Q6b0RkNn2OvixJa9GOl.RZd1NIhUSgAqfqh1AdIMu', 'admin'),
(2, 'guru1', '$2y$10$3aTrWYt5JOOSXzJZgllCpOgSrctQn3PW8YRSl7rJr.u0RJitQsz0K', 'guru'),
(3, 'kepsek1', '$2y$10$3aTrWYt5JOOSXzJZgllCpOgSrctQn3PW8YRSl7rJr.u0RJitQsz0K', 'kepsek');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kriteria`
--
ALTER TABLE `kriteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nilai`
--
ALTER TABLE `nilai`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id_siswa`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kriteria`
--
ALTER TABLE `kriteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `nilai`
--
ALTER TABLE `nilai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id_siswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
