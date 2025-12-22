<?php
include 'db_config.php';
session_start();
include 'cors-config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true && isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $userId = $_SESSION['user_id'];
        $date = $_GET['date'] ?? null;

        if ($date) {
            $getOutfitsQuery = 'SELECT outfit.id, outfit_garment.garment_id,
                            garment.type, garment.sup_type, garment.fabric_type, garment.sleeve, garment.seasons,
                            garment.picture, garment.main_color, garment.pattern, garment.is_second_hand, garment.worn, garment.outfited
                            FROM outfit
                            INNER JOIN outfit_calendar ON outfit.id = outfit_calendar.outfit_id
                            INNER JOIN outfit_garment ON outfit.id = outfit_garment.outfit_id
                            INNER JOIN garment ON outfit_garment.garment_id = garment.id
                            WHERE outfit.user_id = ? AND outfit_calendar.date_worn = ?
                            ORDER BY outfit.id, FIELD(garment.sup_type, "superior", "inferior")';

            $getOutfitsSth = $con->prepare($getOutfitsQuery);

            if (!$getOutfitsSth) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $getOutfitsSth->bind_param('is', $userId, $date);
        } else {
            $getOutfitsQuery = 'SELECT outfit.id, outfit_garment.garment_id,
                            garment.type, garment.sup_type, garment.fabric_type, garment.sleeve, garment.seasons,
                            garment.picture, garment.main_color, garment.pattern, garment.is_second_hand, garment.worn, garment.outfited
                            FROM outfit
                            INNER JOIN outfit_garment ON outfit.id = outfit_garment.outfit_id
                            INNER JOIN garment ON outfit_garment.garment_id = garment.id
                            WHERE outfit.user_id = ?
                            ORDER BY outfit.id, FIELD(garment.sup_type, "superior", "inferior")';

            $getOutfitsSth = $con->prepare($getOutfitsQuery);

            if (!$getOutfitsSth) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $con->error
                ]);
                exit();
            }

            $getOutfitsSth->bind_param('i', $userId);
        }

        if ($getOutfitsSth->execute()) {
            $outfitsResult = $getOutfitsSth->get_result();
            $outfits = [];
            $currentOutfitId = null;
            $currentOutfit = null;

            while ($row = $outfitsResult->fetch_assoc()) {
                if ($currentOutfitId !== $row['id']) {
                    if ($currentOutfit !== null) {
                        $outfits[] = $currentOutfit;
                    }

                    $currentOutfitId = $row['id'];
                    $currentOutfit = [
                        'id' => $row['id'],
                        'garments' => []
                    ];
                }

                $currentOutfit['garments'][] = [
                    'id' => $row['garment_id'],
                    'type' => $row['type'],
                    'supType' => $row['sup_type'],
                    'fabricType' => json_decode($row['fabric_type']),
                    'sleeve' => $row['sleeve'],
                    'seasons' => json_decode($row['seasons']),
                    'picture' => $row['picture'],
                    'mainColor' => $row['main_color'],
                    'pattern' => (bool)$row['pattern'],
                    'isSecondHand' => (bool)$row['is_second_hand'],
                    'worn' => $row['worn'],
                    'outfited' => $row['outfited']
                ];
            }

            if ($currentOutfit !== null) {
                $outfits[] = $currentOutfit;
            }

            if (empty($outfits)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => []
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => $outfits
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $getOutfitsSth->error
            ]);
        }

        $getOutfitsSth->close();
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no loggeado'
    ]);
}
?>
