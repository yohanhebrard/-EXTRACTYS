import os
import random
from fpdf import FPDF
from datetime import datetime

class DesignOffre:
    """
    Classe pour gérer les thèmes et le design des offres commerciales
    Cette classe peut être importée dans le générateur principal
    """
    
    def __init__(self):
        # Dossier des assets
        self.current_dir = os.path.dirname(os.path.abspath(__file__))
        self.assets_dir = os.path.join(self.current_dir, "assets")
        
        # S'assurer que le dossier des assets existe
        os.makedirs(self.assets_dir, exist_ok=True)
        
        # Initialiser les thèmes et layouts
        self.themes = self.initialiser_themes()
        self.layouts = self.initialiser_layouts()
        
        # Sélection aléatoire du thème et du layout
        theme_key = random.choice(list(self.themes.keys()))
        self.current_theme = self.themes[theme_key]
        self.current_layout = random.choice(self.layouts)
        self.theme_name = theme_key
        
        # Éléments visuels
        self.bullet_points = ["•", "➤", "✓", "■"]
        
        # Informations à personnaliser
        self.company_info = {
            "name": "Votre Entreprise SAS",
            "address": "123 Avenue des Affaires, 75008 Paris",
            "email": "contact@votreentreprise.fr",
            "phone": "01 23 45 67 89",
            "website": "www.votreentreprise.fr",
            "slogan": "Solutions sur mesure pour votre entreprise"
        }
        
        # Numéro de l'offre
        self.offer_number = f"OFF-{datetime.now().strftime('%Y%m%d')}-{random.randint(1000, 9999)}"
    
    def initialiser_themes(self):
        """Définit et retourne les thèmes de design disponibles"""
        return {
            "premium_bleu": {
                "primary": (41, 128, 185),        # Bleu professionnel
                "secondary": (236, 240, 241),     # Gris très clair
                "accent": (52, 152, 219),         # Bleu accent
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (44, 62, 80),       # Bleu foncé presque noir
                "font_sizes": {"title": 28, "subtitle": 20, "section": 16, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "corporate_vert": {
                "primary": (39, 174, 96),         # Vert professionnel
                "secondary": (245, 245, 245),     # Gris très clair
                "accent": (46, 204, 113),         # Vert clair
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (51, 51, 51),       # Gris foncé
                "font_sizes": {"title": 26, "subtitle": 18, "section": 16, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "elegant_violet": {
                "primary": (142, 68, 173),        # Violet
                "secondary": (245, 238, 248),     # Violet très clair
                "accent": (155, 89, 182),         # Violet plus clair
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (44, 62, 80),       # Bleu foncé presque noir
                "font_sizes": {"title": 28, "subtitle": 20, "section": 16, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "modern_rouge": {
                "primary": (192, 57, 43),         # Rouge profond
                "secondary": (249, 231, 231),     # Rouge très clair
                "accent": (231, 76, 60),          # Rouge vif
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (51, 51, 51),       # Gris foncé
                "font_sizes": {"title": 28, "subtitle": 20, "section": 16, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "luxe_noir": {
                "primary": (44, 62, 80),          # Bleu très foncé/noir
                "secondary": (236, 240, 241),     # Gris très clair
                "accent": (241, 196, 15),         # Or
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (51, 51, 51),       # Gris foncé
                "font_sizes": {"title": 30, "subtitle": 22, "section": 18, "text": 12, "small": 10},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "tech_bleu": {
                "primary": (52, 73, 94),          # Bleu gris foncé
                "secondary": (236, 240, 241),     # Gris très clair
                "accent": (26, 188, 156),         # Turquoise
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (51, 51, 51),       # Gris foncé
                "font_sizes": {"title": 28, "subtitle": 20, "section": 16, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
            "minimaliste": {
                "primary": (0, 0, 0),             # Noir
                "secondary": (245, 245, 245),     # Gris très clair
                "accent": (180, 180, 180),        # Gris moyen
                "background": (255, 255, 255),    # Fond blanc
                "text_color": (51, 51, 51),       # Gris foncé
                "font_sizes": {"title": 26, "subtitle": 18, "section": 14, "text": 11, "small": 9},
                "fonts": {"title": "helvetica", "body": "helvetica"}
            },
        }
    
    def initialiser_layouts(self):
        """Définit et retourne les layouts disponibles"""
        return [
            "executive",     # Layout professionnel avec barre latérale et mise en page élégante
            "corporate",     # Layout d'entreprise avec en-tête et pied de page structurés
            "modern",        # Layout épuré avec accents de couleur
            "premium",       # Layout premium avec éléments graphiques sophistiqués
            "minimaliste"    # Layout très épuré et simple
        ]
    
    def selectionner_theme(self, theme_nom=None):
        """Sélectionne un thème spécifique ou aléatoire"""
        if theme_nom and theme_nom in self.themes:
            self.current_theme = self.themes[theme_nom]
            self.theme_name = theme_nom
        else:
            theme_key = random.choice(list(self.themes.keys()))
            self.current_theme = self.themes[theme_key]
            self.theme_name = theme_key
        return self.current_theme
    
    def selectionner_layout(self, layout_nom=None):
        """Sélectionne un layout spécifique ou aléatoire"""
        if layout_nom and layout_nom in self.layouts:
            self.current_layout = layout_nom
        else:
            self.current_layout = random.choice(self.layouts)
        return self.current_layout
    
    def definir_informations_entreprise(self, infos):
        """Définit les informations de l'entreprise émettrice"""
        if isinstance(infos, dict):
            for key, value in infos.items():
                if key in self.company_info:
                    self.company_info[key] = value
    
    def format_currency(self, amount):
        """Formate un montant en euros avec séparateur de milliers"""
        return f"{amount:,.2f}".replace(",", " ").replace(".", ",") + " "
    
    def get_date_format(self):
        """Retourne la date formatée en français"""
        months = ["janvier", "février", "mars", "avril", "mai", "juin", 
                  "juillet", "août", "septembre", "octobre", "novembre", "décembre"]
        now = datetime.now()
        return f"{now.day} {months[now.month-1]} {now.year}"
    
    def get_current_settings(self):
        """Retourne les paramètres actuels de design"""
        return {
            "theme": self.theme_name,
            "layout": self.current_layout,
            "offer_number": self.offer_number,
            "company_info": self.company_info
        }
    
    def generer_apercu(self, output_path=None):
        """Génère un aperçu du design actuel"""
        if not output_path:
            output_path = os.path.join(self.current_dir, "apercu_design.pdf")
        
        pdf = FPDF()
        pdf.add_page()
        
        # En-tête avec le nom du thème et du layout
        pdf.set_font("helvetica", "B", 16)
        pdf.cell(0, 10, f"Aperçu du design: Thème '{self.theme_name}' - Layout '{self.current_layout}'", 0, 1, "C")
        pdf.ln(5)
        
        # Échantillons de couleurs
        pdf.set_font("helvetica", "", 12)
        pdf.cell(0, 10, "Palette de couleurs:", 0, 1)
        
        # Primary color
        y = pdf.get_y()
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(20, y, 30, 15, "F")
        pdf.set_xy(55, y+3)
        pdf.cell(100, 10, f"Couleur primaire: RGB{self.current_theme['primary']}")
        
        # Secondary color
        y += 20
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(20, y, 30, 15, "F")
        pdf.set_xy(55, y+3)
        pdf.cell(100, 10, f"Couleur secondaire: RGB{self.current_theme['secondary']}")
        
        # Accent color
        y += 20
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.rect(20, y, 30, 15, "F")
        pdf.set_xy(55, y+3)
        pdf.cell(100, 10, f"Couleur d'accent: RGB{self.current_theme['accent']}")
        
        # Text color
        y += 20
        pdf.set_fill_color(*self.current_theme["text_color"])
        pdf.rect(20, y, 30, 15, "F")
        pdf.set_xy(55, y+3)
        pdf.cell(100, 10, f"Couleur de texte: RGB{self.current_theme['text_color']}")
        
        pdf.ln(25)
        
        # Exemples de typographie
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_font(self.current_theme["fonts"]["title"], "B", self.current_theme["font_sizes"]["title"])
        pdf.cell(0, 10, "Exemple de titre", 0, 1)
        
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_font(self.current_theme["fonts"]["title"], "B", self.current_theme["font_sizes"]["subtitle"])
        pdf.cell(0, 10, "Exemple de sous-titre", 0, 1)
        
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_font(self.current_theme["fonts"]["title"], "B", self.current_theme["font_sizes"]["section"])
        pdf.cell(0, 10, "Exemple de titre de section", 0, 1)
        
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_font(self.current_theme["fonts"]["body"], "", self.current_theme["font_sizes"]["text"])
        pdf.multi_cell(0, 8, "Exemple de texte courant. Ceci est un exemple du texte standard qui sera utilisé dans le document pour présenter les informations principales de l'offre commerciale.")
        
        pdf.ln(5)
        
        # Exemples d'éléments de design
        # Rectangle stylisé selon le thème
        y = pdf.get_y()
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.5)
        pdf.rect(20, y, 170, 30, "FD")
        
        pdf.set_xy(25, y+5)
        pdf.set_font(self.current_theme["fonts"]["body"], "B", self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(160, 8, "Exemple de zone d'information", 0, 1)
        
        pdf.set_xy(25, y+15)
        pdf.set_font(self.current_theme["fonts"]["body"], "", self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(160, 8, "Contenu de la zone: Données de l'offre", 0, 1)
        
        pdf.ln(10)
        
        # Exemples de bullet points
        pdf.set_font(self.current_theme["fonts"]["body"], "", self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(0, 10, "Exemples de bullet points:", 0, 1)
        
        for i, bullet in enumerate(self.bullet_points):
            pdf.set_x(25)
            pdf.set_text_color(*self.current_theme["primary"])
            pdf.cell(10, 8, bullet)
            pdf.set_text_color(*self.current_theme["text_color"])
            pdf.cell(0, 8, f"Exemple d'élément à puce {i+1}", 0, 1)
        
        # Sauvegarde du PDF
        pdf.output(output_path)
        return output_path


# Exemple d'utilisation
if __name__ == "__main__":
    # Instancier la classe de design
    design = DesignOffre()
    
    # Sélectionner un thème et un layout spécifiques
    design.selectionner_theme("premium_bleu")
    design.selectionner_layout("executive")
    
    # Personnaliser les informations de l'entreprise
    design.definir_informations_entreprise({
        "name": "Ma Société SAS",
        "slogan": "L'excellence au service de votre entreprise"
    })
    
    # Générer un aperçu
    apercu_path = design.generer_apercu()
    print(f"Aperçu généré: {apercu_path}")
    
    # Afficher les paramètres actuels
    print(design.get_current_settings())