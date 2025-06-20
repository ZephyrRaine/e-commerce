<?php
// Connexion à la base de données via PDO
$pdo = new PDO("mysql:host=10.96.16.82;dbname=magasin;charset=utf8", "colin", "");

// Inclusion du header (structure HTML, menu, etc.)
require_once('header.php');

// Récupération de toutes les catégories dans la base de données
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Initialisation du tableau des articles
$articles = [];

// Si l'URL contient un paramètre 'categorie'
if (isset($_GET['categorie'])) {
    // On convertit l'identifiant de la catégorie en entier (sécurité)
    $categorieId = (int) $_GET['categorie'];
    
    // Préparation de la requête pour récupérer les articles liés à cette catégorie
    $stmt = $pdo->prepare("
        SELECT * FROM articles 
        WHERE id_categorie = :id_categorie
    ");
    
    // Exécution de la requête avec liaison de paramètre
    $stmt->execute(['id_categorie' => $categorieId]);

    // Récupération des articles sous forme de tableau associatif
    $articles = $stmt->fetchAll();
}
// On crée un tableau associatif id_article => [notes...]
$notations = [];
if (!empty($articles)) {
    $ids = implode(',', array_column($articles, 'id_article'));
    $stmt = $pdo->query("SELECT * FROM notation WHERE id_article IN ($ids)");
    $allNotes = $stmt->fetchAll();

    foreach ($allNotes as $note) {
        $id = $note['id_article'];
        if (!isset($notations[$id])) $notations[$id] = [];
        $notations[$id][] = $note;
    }
}

?>

<style>
    h1, h2 {
        color: #2c3e50;
    }

    .categories {
        margin: 20px 0;
        padding: 10px 0;
        border-bottom: 1px solid #ccc;
    }

    .categories a {
        display: inline-block;
        background-color: #3498db;
        color: white;
        padding: 8px 14px;
        margin: 5px 10px 5px 0;
        border-radius: 5px;
        text-decoration: none;
        transition: 0.3s;
    }

    .categories a:hover {
        background-color: #2980b9;
    }

    .article {
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .article strong {
        font-size: 1.1em;
        display: block;
        margin-bottom: 5px;
    }
</style>

<!-- Titre principal -->
<h1>Catégories</h1>

<!-- Liste des catégories sous forme de boutons -->
<div class="categories">
    <?php foreach ($categories as $cat): ?>
        <!-- Lien vers la même page avec l'ID de la catégorie en paramètre GET -->
        <a href="?categorie=<?= $cat['id_categorie'] ?>">
            <?= htmlspecialchars($cat['nom']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Si une catégorie a été sélectionnée -->
<?php if (isset($_GET['categorie'])): ?>
    <h2>Articles de la catégorie : 
        <!-- Affiche le nom de la catégorie actuelle -->
        <?= htmlspecialchars($categories[array_search($categorieId, array_column($categories, 'id_categorie'))]['nom']) ?>
    </h2>

    <!-- Si aucun article dans cette catégorie -->
    <?php if (count($articles) === 0): ?>
        <p>Aucun article dans cette catégorie.</p>

    <!-- Sinon, affichage des articles -->
    <?php else: ?>
        <?php foreach ($articles as $article): ?>
    <div class="article">
        <strong><?= htmlspecialchars($article['produit']) ?></strong>
        <br>Prix : <?= number_format($article['prix'], 2, ',', ' ') ?> €
        
        <?php
        $id = $article['id_article'];
        if (isset($notations[$id])) {
            $notes = array_column($notations[$id], 'note');
            $moyenne = round(array_sum($notes) / count($notes), 1);
            echo "<p>Note moyenne : <strong>$moyenne/5</strong></p>";

            // Affiche les avis
            echo "<ul style='margin: 0; padding-left: 20px;'>";
            foreach ($notations[$id] as $n) {
                echo "<li><em>« " . htmlspecialchars($n['avis']) . " »</em></li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Aucune évaluation</p>";
        }
        ?>

        <form class="add-to-cart-form" data-id="<?= $article['id_article'] ?>" style="margin-top: 10px;">
            <button type="submit">Ajouter au panier 🛒</button>
        </form>
    </div>
<?php endforeach; ?>

    <?php endif; ?>
<?php endif; ?>

<script>
document.querySelectorAll('.add-to-cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const id = this.dataset.id;
        const button = this.querySelector('button');
        const originalText = button.textContent;

        // Désactive temporairement pour éviter les spams
        button.disabled = true;
        button.textContent = "✅ Ajouté";

        fetch('add_to_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_article=' + encodeURIComponent(id)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const panierLink = document.querySelector('a[href="panier.php"]');
                if (panierLink) {
                    panierLink.textContent = "Panier (" + data.total + ")";
                }

                // Revenir à l'état initial après 3 secondes
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = originalText;
                }, 3000);
            } else {
                button.textContent = "Erreur";
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = originalText;
                }, 3000);
            }
        })
        .catch(() => {
            button.textContent = "⚠️ Erreur réseau";
            setTimeout(() => {
                button.disabled = false;
                button.textContent = originalText;
            }, 1500);
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
