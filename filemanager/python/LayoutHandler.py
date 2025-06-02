import os
from fpdf import FPDF
import unidecode
import json

class LayoutHandler:
    """
    Classe pour gérer les différents layouts des offres commerciales
    Chaque méthode implémente un style de mise en page différent
    """
    
    def __init__(self, design):
        """
        Initialise le gestionnaire de layouts avec les informations de design
        
        Args:
            design (DesignOffre): L'objet contenant les informations de design
        """
        self.design = design
        self.current_theme = design.current_theme
        self.current_layout = design.current_layout
        self.company_info = design.company_info
        self.offer_number = design.offer_number
    
    def log_debug(self, message):
        """Utilitaire pour afficher des messages de débogage au format JSON"""
        print(json.dumps({"debug": str(message)}))
    
    def format_currency(self, amount):
        """Formate un montant en euros"""
        return self.design.format_currency(amount)
    
    def add_header(self, pdf):
        """
        Ajoute l'en-tête au PDF selon le layout sélectionné
        
        Args:
            pdf (FPDF): L'objet PDF
        """
        layout_method = getattr(self, f"header_{self.current_layout}", self.header_default)
        layout_method(pdf)
    
    def header_executive(self, pdf):
        """Layout Executive - Élégant avec barre latérale"""
        # Barre latérale colorée
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(0, 0, 25, 297, 'F')
        
        # Logo et nom de l'entreprise
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(35, 15)
        pdf.cell(0, 10, self.company_info["name"], 0, 1, 'L')
        
        # Slogan
        pdf.set_font(self.current_theme["fonts"]["body"], 'I', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(35, 25)
        pdf.cell(0, 10, self.company_info["slogan"], 0, 1, 'L')
        
        # Titre du document
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["subtitle"])
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(35, 40)
        pdf.cell(160, 12, "PROPOSITION COMMERCIALE", 0, 1, 'L', 1)
        
        # Numéro d'offre et date
        pdf.set_xy(35, 55)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(0, 10, f"Référence : {self.offer_number} | Date : {self.design.get_date_format()}", 0, 1, 'L')
    
    def header_corporate(self, pdf):
        """Layout Corporate - Structuré et formel"""
        # Bandeau en haut
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(0, 0, 210, 35, 'F')
        
        # Titre du document
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(15, 12)
        pdf.cell(180, 10, "PROPOSITION COMMERCIALE", 0, 1, 'C')
        
        # Informations de l'entreprise
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(0, 35, 210, 25, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, 40)
        pdf.cell(90, 8, self.company_info["name"], 0, 0, 'L')
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_xy(15, 48)
        pdf.cell(90, 8, self.company_info["address"], 0, 0, 'L')
        
        pdf.set_xy(140, 40)
        pdf.cell(60, 8, f"Référence : {self.offer_number}", 0, 0, 'R')
        
        pdf.set_xy(140, 48)
        pdf.cell(60, 8, f"Date : {self.design.get_date_format()}", 0, 0, 'R')
    
    def header_modern(self, pdf):
        """Layout Modern - Épuré avec accents de couleur"""
        # En-tête minimaliste avec accent de couleur
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.rect(0, 0, 210, 10, 'F')
        
        # Titre et informations
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(15, 20)
        pdf.cell(0, 10, "PROPOSITION COMMERCIALE", 0, 1, 'L')
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, 33)
        pdf.cell(0, 8, f"{self.company_info['name']} - {self.company_info['address']}", 0, 1, 'L')
        
        pdf.set_xy(15, 41)
        pdf.cell(100, 8, f"Tél: {self.company_info['phone']} | Email: {self.company_info['email']}", 0, 0, 'L')
        
        pdf.set_xy(150, 41)
        pdf.cell(45, 8, f"Ref: {self.offer_number}", 0, 0, 'R')
    
    def header_premium(self, pdf):
        """Layout Premium - Élégant et sophistiqué"""
        # Fond élégant pour l'en-tête
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(0, 0, 210, 45, 'F')
        
        # Titre avec style élégant
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(15, 15)
        pdf.cell(180, 10, "PROPOSITION COMMERCIALE", 0, 1, 'C')
        
        # Sous-titre
        pdf.set_font(self.current_theme["fonts"]["body"], 'I', self.current_theme["font_sizes"]["subtitle"])
        pdf.set_xy(15, 28)
        pdf.cell(180, 10, self.company_info["slogan"], 0, 1, 'C')
        
        # Informations importantes
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(15, 50, 180, 20, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(20, 55)
        pdf.cell(85, 10, f"Référence: {self.offer_number}", 0, 0, 'L')
        
        pdf.set_xy(20, 60)
        pdf.cell(85, 10, f"Date: {self.design.get_date_format()}", 0, 0, 'L')
        
        pdf.set_xy(120, 55)
        pdf.cell(70, 10, f"Contact: {self.company_info['phone']}", 0, 0, 'R')
        
        pdf.set_xy(120, 60)
        pdf.cell(70, 10, self.company_info['email'], 0, 0, 'R')
    
    def header_minimaliste(self, pdf):
        """Layout Minimaliste - Très épuré et simple"""
        # Ligne fine en haut
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(1)
        pdf.line(15, 10, 195, 10)
        
        # Nom de l'entreprise
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(15, 15)
        pdf.cell(180, 10, self.company_info["name"], 0, 1, 'L')
        
        # Titre du document
        pdf.set_font(self.current_theme["fonts"]["title"], '', self.current_theme["font_sizes"]["subtitle"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, 30)
        pdf.cell(180, 10, "Proposition Commerciale", 0, 1, 'L')
        
        # Informations essentielles
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_xy(15, 45)
        pdf.cell(90, 5, f"Réf. {self.offer_number}", 0, 0, 'L')
        
        pdf.set_xy(15, 50)
        pdf.cell(90, 5, f"Date: {self.design.get_date_format()}", 0, 0, 'L')
    
    def header_default(self, pdf):
        """Header par défaut si le layout spécifié n'est pas trouvé"""
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(0, 0, 210, 30, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["title"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(10, 10)
        pdf.cell(190, 10, "PROPOSITION COMMERCIALE", 0, 1, 'C')
    
    def format_section(self, pdf, title):
        """
        Formate un titre de section selon le layout sélectionné
        
        Args:
            pdf (FPDF): L'objet PDF
            title (str): Le titre de la section
        """
        layout_method = getattr(self, f"section_{self.current_layout}", self.section_default)
        layout_method(pdf, title)
    
    def section_executive(self, pdf, title):
        """Style de section pour layout Executive"""
        y_pos = pdf.get_y()
        
        # Style executive avec barre verticale
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(1.5)
        pdf.line(30, y_pos, 30, y_pos + 12)
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(35, y_pos)
        pdf.cell(165, 12, title, 0, 1, 'L')
        
        pdf.set_draw_color(*self.current_theme["accent"])
        pdf.set_line_width(0.5)
        pdf.line(35, y_pos + 12, 200, y_pos + 12)
    
    def section_corporate(self, pdf, title):
        """Style de section pour layout Corporate"""
        y_pos = pdf.get_y()
        
        # Style corporate avec fond coloré
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(15, y_pos, 180, 10, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(20, y_pos + 2)
        pdf.cell(170, 8, title, 0, 1, 'L')
    
    def section_modern(self, pdf, title):
        """Style de section pour layout Modern"""
        y_pos = pdf.get_y()
        
        # Style moderne avec accent de couleur à gauche
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.rect(15, y_pos, 5, 12, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(25, y_pos)
        pdf.cell(175, 12, title, 0, 1, 'L')
        
        pdf.set_draw_color(*self.current_theme["secondary"])
        pdf.set_line_width(0.5)
        pdf.line(25, y_pos + 12, 200, y_pos + 12)
    
    def section_premium(self, pdf, title):
        """Style de section pour layout Premium"""
        y_pos = pdf.get_y()
        
        # Style premium avec dégradé
        for i in range(10):
            shade = tuple(map(lambda x: max(0, min(255, x - (10-i)*10)), self.current_theme["primary"]))
            pdf.set_fill_color(*shade)
            pdf.rect(15 + i*18, y_pos, 18, 12, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(20, y_pos + 2)
        pdf.cell(170, 8, title, 0, 1, 'L')
    
    def section_minimaliste(self, pdf, title):
        """Style de section pour layout Minimaliste"""
        y_pos = pdf.get_y()
        
        # Style minimaliste avec ligne simple
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(15, y_pos)
        pdf.cell(180, 8, title, 0, 1, 'L')
        
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.3)
        pdf.line(15, y_pos + 10, 100, y_pos + 10)
    
    def section_default(self, pdf, title):
        """Style de section par défaut"""
        y_pos = pdf.get_y()
        
        pdf.set_font(self.current_theme["fonts"]["title"], 'B', self.current_theme["font_sizes"]["section"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(15, y_pos)
        pdf.cell(180, 10, title, 0, 1, 'L')
        
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.5)
        pdf.line(15, y_pos + 12, 195, y_pos + 12)
    
    def format_info_box(self, pdf, label, value, y_pos=None, box_height=15):
        """
        Affiche une information dans une boîte stylisée
        
        Args:
            pdf (FPDF): L'objet PDF
            label (str): L'étiquette de l'information
            value (str): La valeur de l'information
            y_pos (int, optional): Position Y. Si None, utilise la position actuelle.
            box_height (int, optional): Hauteur de la boîte. Par défaut 15.
        """
        layout_method = getattr(self, f"info_box_{self.current_layout}", self.info_box_default)
        return layout_method(pdf, label, value, y_pos, box_height)
    
    def info_box_executive(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information pour layout Executive"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(35, y_pos, 160, box_height, 'F')
        
        pdf.set_xy(40, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        pdf.set_xy(120, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(75, box_height - 6, value, 0, 0, 'R')
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def info_box_corporate(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information pour layout Corporate"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        # Ligne alternée
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(15, y_pos, 180, box_height, 'F')
        
        # Texte
        pdf.set_xy(20, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        pdf.set_xy(100, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(95, box_height - 6, value, 0, 0, 'R')
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def info_box_modern(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information pour layout Modern"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        # Style moderne avec accent à gauche
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(15, y_pos, 180, box_height, 'F')
        
        # Barre d'accent
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.rect(15, y_pos, 3, box_height, 'F')
        
        # Texte
        pdf.set_xy(25, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        pdf.set_xy(105, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(90, box_height - 6, value, 0, 0, 'R')
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def info_box_premium(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information pour layout Premium"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        # Style élégant avec bordure
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.3)
        pdf.rect(15, y_pos, 180, box_height, 'FD')
        
        # Étiquette
        pdf.set_xy(20, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        # Valeur
        pdf.set_xy(100, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(95, box_height - 6, value, 0, 0, 'R')
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def info_box_minimaliste(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information pour layout Minimaliste"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        # Style minimaliste sans bordure ni remplissage
        pdf.set_xy(15, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        pdf.set_xy(95, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(100, box_height - 6, value, 0, 0, 'R')
        
        # Ligne fine de séparation
        pdf.set_draw_color(220, 220, 220)
        pdf.set_line_width(0.1)
        pdf.line(15, y_pos + box_height, 195, y_pos + box_height)
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def info_box_default(self, pdf, label, value, y_pos=None, box_height=15):
        """Style de boîte d'information par défaut"""
        if y_pos is None:
            y_pos = pdf.get_y()
        
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(15, y_pos, 180, box_height, 'F')
        
        pdf.set_xy(20, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.cell(80, box_height - 6, label, 0, 0)
        
        pdf.set_xy(100, y_pos + 3)
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.cell(95, box_height - 6, value, 0, 0, 'R')
        
        pdf.set_y(y_pos + box_height)
        return y_pos + box_height
    
    def advantage_executive(self, pdf, advantage, index):
        """Style d'avantage pour layout Executive"""
        y_pos = pdf.get_y()
        row_height = 20
        
        # Choix du bullet point
        bullet = self.design.bullet_points[index % len(self.design.bullet_points)]
        
        # Fond alterné
        if index % 2 == 0:
            pdf.set_fill_color(*self.current_theme["secondary"])
            pdf.rect(35, y_pos, 160, row_height, 'F')
        
        # Bullet point décoratif
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(38, y_pos + 4)
        pdf.cell(10, 6, bullet)
        
        # Texte de l'avantage
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(48, y_pos + 4)
        pdf.multi_cell(145, 6, advantage)
        
        # Positionne le curseur après l'élément
        next_y = y_pos + max(row_height, 10)  # Minimum 10pt de hauteur
        pdf.set_y(next_y)
        
        return next_y
    
    def advantage_corporate(self, pdf, advantage, index):
        """Style d'avantage pour layout Corporate"""
        y_pos = pdf.get_y()
        row_height = 20
        
        # Ligne de couleur alternée
        background_color = self.current_theme["secondary"] if index % 2 == 0 else (248, 248, 248)
        pdf.set_fill_color(*background_color)
        pdf.rect(15, y_pos, 180, row_height, 'F')
        
        # Petit carré de couleur pour le bullet
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(20, y_pos + 5, 5, 5, 'F')
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(30, y_pos + 4)
        pdf.multi_cell(165, 6, advantage)
        
        # Positionne le curseur après l'élément
        next_y = y_pos + max(row_height, 10)
        pdf.set_y(next_y)
        
        return next_y
    
    def advantage_modern(self, pdf, advantage, index):
        """Style d'avantage pour layout Modern"""
        y_pos = pdf.get_y()
        row_height = 20
        
        # Style épuré avec accent
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(15, y_pos, 180, row_height, 'F')
        
        # Numéro stylisé
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.set_xy(20, y_pos + 4)
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(255, 255, 255)
        
        # Cercle avec numéro
        pdf.ellipse(25, y_pos + 6, 5, 5, 'F')
        pdf.set_xy(21, y_pos + 4)
        pdf.cell(8, 6, str(index+1))
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(35, y_pos + 4)
        pdf.multi_cell(160, 6, advantage)
        
        # Positionne le curseur
        next_y = y_pos + max(row_height, 10)
        pdf.set_y(next_y)
        
        return next_y
    def advantage_premium(self, pdf, advantage, index):
        """Style d'avantage pour layout Premium"""
        y_pos = pdf.get_y()
        row_height = 20
        
        # Style élégant
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.set_draw_color(*self.current_theme["accent"])
        pdf.set_line_width(0.2)
        pdf.rect(15, y_pos, 180, row_height, 'FD')
        
        # Icône élégante
        pdf.set_font('zapfdingbats', '', 12)
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(20, y_pos + 4)
        pdf.cell(10, 6, "❖")
        
        # Texte de l'avantage
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(30, y_pos + 4)
        pdf.multi_cell(165, 6, advantage)
        
        # Positionne le curseur
        next_y = y_pos + max(row_height, 10)
        pdf.set_y(next_y)
        
        return next_y
    
    def advantage_minimaliste(self, pdf, advantage, index):
        """Style d'avantage pour layout Minimaliste"""
        y_pos = pdf.get_y()
        row_height = 18
        
        # Style minimaliste sans fond
        if index % 2 == 0:
            pdf.set_fill_color(250, 250, 250)
            pdf.rect(15, y_pos, 180, row_height, 'F')
        
        # Point simple
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(20, y_pos + 4)
        pdf.cell(5, 6, "•")
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(28, y_pos + 4)
        pdf.multi_cell(167, 6, advantage)
        
        # Ligne fine de séparation
        pdf.set_draw_color(240, 240, 240)
        pdf.set_line_width(0.1)
        pdf.line(28, y_pos + row_height - 1, 195, y_pos + row_height - 1)
        
        # Positionne le curseur
        next_y = y_pos + max(row_height, 10)
        pdf.set_y(next_y)
        
        return next_y
    
    def advantage_default(self, pdf, advantage, index):
        """Style d'avantage par défaut"""
        y_pos = pdf.get_y()
        
        # Bullet point
        bullet = "•"
        
        # Fond alterné
        if index % 2 == 0:
            pdf.set_fill_color(*self.current_theme["secondary"])
            pdf.rect(15, y_pos, 180, 20, 'F')
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(20, y_pos + 4)
        pdf.cell(10, 6, bullet)
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(30, y_pos + 4)
        pdf.multi_cell(165, 6, advantage)
        
        next_y = y_pos + 20
        pdf.set_y(next_y)
        
        return next_y
    
    def generate_comparison_table(self, pdf, data):
        """Génère un tableau comparatif entre l'offre actuelle et la nouvelle offre"""
        y_pos = pdf.get_y()
        
        # Titre de la section
        self.format_section(pdf, "Comparaison des offres")
        pdf.ln(5)
        
        # Entêtes du tableau
        header_height = 12
        col_width = 60
        
        # Style selon le layout
        if self.current_layout in ["executive", "premium"]:
            # En-têtes stylisés
            pdf.set_fill_color(*self.current_theme["primary"])
            pdf.rect(35, pdf.get_y(), col_width, header_height, 'F')
            pdf.rect(35 + col_width, pdf.get_y(), col_width, header_height, 'F')
            pdf.rect(35 + 2*col_width, pdf.get_y(), col_width, header_height, 'F')
            
            # Texte des en-têtes
            pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
            pdf.set_text_color(255, 255, 255)
            
            pdf.set_xy(35, pdf.get_y() + 3)
            pdf.cell(col_width, header_height - 3, "Détails", 0, 0, 'C')
            
            pdf.set_xy(35 + col_width, pdf.get_y())
            pdf.cell(col_width, header_height - 3, f"Offre actuelle", 0, 0, 'C')
            
            pdf.set_xy(35 + 2*col_width, pdf.get_y())
            pdf.cell(col_width, header_height - 3, "Notre proposition", 0, 0, 'C')
            
            pdf.ln(header_height)
            
            # Lignes du tableau
            rows = [
                ["Prestataire", data.get("prestataire", ""), self.company_info["name"]],
                ["Montant mensuel", self.format_currency(data.get("montant_ttc", 0)), 
                 self.format_currency(data.get("montant_ttc", 0) - data.get("economie_mensuelle", 0))],
                ["Économie mensuelle", "-", self.format_currency(data.get("economie_mensuelle", 0))],
                ["Économie annuelle", "-", self.format_currency(data.get("economie_annuelle", 0))],
                ["Engagement", "Variable", data.get("conditions", "3 mois d'engagement")]
            ]
            
            for i, row in enumerate(rows):
                bg_color = self.current_theme["secondary"] if i % 2 == 0 else (248, 248, 248)
                pdf.set_fill_color(*bg_color)
                
                # Cellules du tableau
                for j, cell in enumerate(row):
                    # Style différent selon la colonne
                    if j == 0:
                        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
                        pdf.set_text_color(*self.current_theme["primary"])
                    else:
                        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
                        pdf.set_text_color(*self.current_theme["text_color"])
                    
                    # Mise en évidence de la dernière colonne (notre offre)
                    if j == 2:
                        pdf.set_text_color(*self.current_theme["primary"])
                        if i in [1, 2, 3]:  # Montants et économies
                            pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
                    
                    # Dessine la cellule avec remplissage
                    pdf.rect(35 + j*col_width, pdf.get_y(), col_width, 10, 'F')
                    pdf.set_xy(35 + j*col_width, pdf.get_y())
                    pdf.cell(col_width, 10, str(cell), 0, 0, 'C')
                
                pdf.ln(10)
        
        else:  # corporate ou modern
            # Style plus simple pour les autres layouts
            table_width = 180
            col_width = table_width / 3
            
            # En-têtes
            pdf.set_fill_color(*self.current_theme["primary"])
            pdf.rect(15, pdf.get_y(), table_width, header_height, 'F')
            
            pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
            pdf.set_text_color(255, 255, 255)
            
            pdf.set_xy(15, pdf.get_y() + 3)
            pdf.cell(col_width, header_height - 3, "Détails", 0, 0, 'C')
            
            pdf.set_xy(15 + col_width, pdf.get_y())
            pdf.cell(col_width, header_height - 3, f"Offre actuelle", 0, 0, 'C')
            
            pdf.set_xy(15 + 2*col_width, pdf.get_y())
            pdf.cell(col_width, header_height - 3, "Notre proposition", 0, 0, 'C')
            
            pdf.ln(header_height)
            
            # Contenu du tableau
            rows = [
                ["Prestataire", data.get("prestataire", ""), self.company_info["name"]],
                ["Montant mensuel", self.format_currency(data.get("montant_ttc", 0)), 
                 self.format_currency(data.get("montant_ttc", 0) - data.get("economie_mensuelle", 0))],
                ["Économie mensuelle", "-", self.format_currency(data.get("economie_mensuelle", 0))],
                ["Économie annuelle", "-", self.format_currency(data.get("economie_annuelle", 0))],
                ["Engagement", "Variable", data.get("conditions", "3 mois d'engagement")]
            ]
            
            for i, row in enumerate(rows):
                # Alternance des couleurs de fond
                bg_color = self.current_theme["secondary"] if i % 2 == 0 else (245, 245, 245)
                pdf.set_fill_color(*bg_color)
                
                for j, cell in enumerate(row):
                    # Rectangle de fond
                    pdf.rect(15 + j*col_width, pdf.get_y(), col_width, 10, 'F')
                    
                    # Style du texte selon la colonne
                    if j == 0:
                        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
                        pdf.set_text_color(*self.current_theme["text_color"])
                    elif j == 2:  # Notre proposition (mise en évidence)
                        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
                        pdf.set_text_color(*self.current_theme["primary"])
                    else:
                        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
                        pdf.set_text_color(*self.current_theme["text_color"])
                    
                    # Texte
                    pdf.set_xy(15 + j*col_width + 5, pdf.get_y() + 2)  # Marge à gauche dans la cellule
                    pdf.cell(col_width - 10, 6, str(cell), 0, 0, 'L' if j == 0 else 'R')
                
                pdf.ln(10)
        
        # Ajout d'espace après le tableau
        pdf.ln(5)
        return pdf.get_y()
    
    def generate_savings_chart(self, pdf, data):
        """Génère un graphique illustrant les économies sur 12 mois"""
        try:
            y_pos = pdf.get_y()
            
            # Titre de la section
            self.format_section(pdf, "Projection des économies sur 12 mois")
            pdf.ln(10)
            
            # Dimensions du graphique
            chart_width = 160
            chart_height = 80
            
            if self.current_layout == "executive":
                chart_x = 35
            else:
                chart_x = 25
                
            chart_y = pdf.get_y()
            
            # Calculs des économies
            monthly_savings = data.get("economie_mensuelle", 0)
            current_monthly = data.get("montant_ttc", 0)
            new_monthly = current_monthly - monthly_savings
            
            # Données pour le graphique (cumul des économies sur 12 mois)
            months = ["Jan", "Fév", "Mar", "Avr", "Mai", "Juin", "Juil", "Août", "Sept", "Oct", "Nov", "Déc"]
            cumulative_savings = [monthly_savings * (i+1) for i in range(12)]
            
            # Maximum pour l'échelle
            max_savings = cumulative_savings[-1]
            
            # Fond du graphique
            pdf.set_fill_color(248, 248, 248)
            pdf.rect(chart_x, chart_y, chart_width, chart_height, 'F')
            
            # Axe X (mois)
            pdf.set_line_width(0.2)
            pdf.set_draw_color(180, 180, 180)
            pdf.line(chart_x, chart_y + chart_height, chart_x + chart_width, chart_y + chart_height)
            
            # Étiquettes des mois
            pdf.set_font(self.current_theme["fonts"]["body"], '', 8)
            pdf.set_text_color(100, 100, 100)
            
            month_width = chart_width / 12
            for i, month in enumerate(months):
                x = chart_x + i * month_width + month_width/2
                pdf.set_xy(x - 5, chart_y + chart_height + 2)
                pdf.cell(10, 5, month, 0, 0, 'C')
            
            # Axe Y (économies)
            pdf.line(chart_x, chart_y, chart_x, chart_y + chart_height)
            
            # Graduations Y
            for i in range(5):
                y = chart_y + chart_height - (i * chart_height / 4)
                pdf.line(chart_x - 2, y, chart_x, y)
                
                value = max_savings * i / 4
                pdf.set_xy(chart_x - 20, y - 2)
                pdf.cell(15, 5, self.format_currency(value), 0, 0, 'R')
            
            # Barres des économies cumulées
            bar_width = month_width * 0.6
            
            for i, saving in enumerate(cumulative_savings):
                bar_height = (saving / max_savings) * chart_height
                
                x = chart_x + i * month_width + month_width/2 - bar_width/2
                y = chart_y + chart_height - bar_height
                
                # Dégradé de couleur selon la progression
                intensity = 0.3 + (0.7 * i / 11)  # De 30% à 100% d'intensité
                r, g, b = self.current_theme["primary"]
                bar_color = (int(r * intensity), int(g * intensity), int(b * intensity))
                
                pdf.set_fill_color(*bar_color)
                pdf.rect(x, y, bar_width, bar_height, 'F')
                
                # Étiquette de valeur pour les mois clés (3, 6, 12)
                if i in [2, 5, 11]:
                    pdf.set_font(self.current_theme["fonts"]["body"], 'B', 8)
                    pdf.set_text_color(*self.current_theme["primary"])
                    pdf.set_xy(x - 5, y - 8)
                    pdf.cell(bar_width + 10, 5, self.format_currency(saving), 0, 0, 'C')
            
            # Légende
            legend_y = chart_y + chart_height + 15
            
            pdf.set_font(self.current_theme["fonts"]["body"], 'B', 10)
            pdf.set_text_color(*self.current_theme["text_color"])
            pdf.set_xy(chart_x, legend_y)
            pdf.cell(chart_width, 10, "Économies cumulées: " + self.format_currency(max_savings) + " sur 12 mois", 0, 1, 'C')
            
            # Texte explicatif
            pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
            pdf.set_xy(chart_x, legend_y + 10)
            pdf.multi_cell(chart_width, 5, 
                          f"En choisissant notre offre, vous économisez {self.format_currency(monthly_savings)} chaque mois, " +
                          f"soit {self.format_currency(monthly_savings*12)} sur un an, tout en bénéficiant d'un service de qualité supérieure.",
                          0, 'L')
            
            # Positionner après le graphique et la légende
            pdf.set_y(legend_y + 25)
            
        except Exception as e:
            self.log_debug(f"Erreur lors de la génération du graphique: {str(e)}")
            pdf.set_y(y_pos + 5)  # En cas d'erreur, simplement ajouter un peu d'espace
        
        return pdf.get_y()
    
    def add_footer(self, pdf):
        """Ajoute un pied de page au PDF selon le layout sélectionné"""
        # Sauvegarde de la position actuelle
        current_y = pdf.get_y()
        
        # Position du pied de page
        footer_y = 270  # Fixé en bas de page
        
        # Style selon le layout
        layout_method = getattr(self, f"footer_{self.current_layout}", self.footer_default)
        layout_method(pdf, footer_y)
        
        # Restaurer la position
        pdf.set_y(current_y)
    
    def footer_executive(self, pdf, footer_y):
        """Pied de page pour layout Executive"""
        # Ligne de séparation
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.5)
        pdf.line(35, footer_y - 5, 195, footer_y - 5)
        
        # Informations de contact
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(35, footer_y)
        pdf.cell(160, 5, f"{self.company_info['name']} | {self.company_info['address']} | {self.company_info['phone']}", 0, 1, 'C')
        
        pdf.set_xy(35, footer_y + 5)
        pdf.cell(160, 5, f"{self.company_info['email']} | {self.company_info['website']}", 0, 1, 'C')
        
        # Date et référence
        pdf.set_font(self.current_theme["fonts"]["body"], 'I', self.current_theme["font_sizes"]["small"])
        pdf.set_xy(35, footer_y + 12)
        pdf.cell(160, 5, f"Offre {self.offer_number} créée le {self.design.get_date_format()}", 0, 1, 'C')
    
    def footer_corporate(self, pdf, footer_y):
        """Pied de page pour layout Corporate"""
        # Bandeau en bas
        pdf.set_fill_color(*self.current_theme["primary"])
        pdf.rect(0, footer_y, 210, 25, 'F')
        
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(15, footer_y + 5)
        pdf.cell(180, 5, self.company_info["name"], 0, 1, 'C')
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_xy(15, footer_y + 10)
        pdf.cell(180, 5, f"{self.company_info['address']} | {self.company_info['phone']} | {self.company_info['email']}", 0, 1, 'C')
        
        pdf.set_xy(15, footer_y + 15)
        pdf.cell(180, 5, f"Référence: {self.offer_number} | Document généré le {self.design.get_date_format()}", 0, 1, 'C')
    
    def footer_modern(self, pdf, footer_y):
        """Pied de page pour layout Modern"""
        # Ligne d'accent
        pdf.set_fill_color(*self.current_theme["accent"])
        pdf.rect(15, footer_y - 3, 180, 2, 'F')
        
        # Texte minimaliste
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, footer_y)
        pdf.cell(90, 5, f"{self.company_info['name']} | {self.company_info['phone']}", 0, 0, 'L')
        
        pdf.set_xy(105, footer_y)
        pdf.cell(90, 5, f"Réf: {self.offer_number} | {self.design.get_date_format()}", 0, 0, 'R')
        
        pdf.set_xy(15, footer_y + 5)
        pdf.cell(180, 5, f"{self.company_info['address']} | {self.company_info['email']} | {self.company_info['website']}", 0, 0, 'C')
    
    def footer_premium(self, pdf, footer_y):
        """Pied de page pour layout Premium"""
        # Fond dégradé
        for i in range(10):
            intensity = i / 10
            shade = tuple(map(lambda x: 255 - (255 - x) * intensity, self.current_theme["primary"]))
            pdf.set_fill_color(*shade)
            pdf.rect(0, footer_y + i*2, 210, 2, 'F')
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(255, 255, 255)
        pdf.set_xy(15, footer_y + 3)
        pdf.cell(180, 5, self.company_info["name"], 0, 1, 'C')
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_xy(15, footer_y + 8)
        pdf.cell(180, 5, f"{self.company_info['address']} | {self.company_info['phone']} | {self.company_info['email']}", 0, 1, 'C')
        
        pdf.set_xy(15, footer_y + 13)
        pdf.cell(180, 5, f"Offre commerciale référence {self.offer_number} | {self.design.get_date_format()}", 0, 1, 'C')
    
    def footer_minimaliste(self, pdf, footer_y):
        """Pied de page pour layout Minimaliste"""
        # Ligne simple
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.3)
        pdf.line(15, footer_y, 195, footer_y)
        
        # Texte minimaliste
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, footer_y + 3)
        pdf.cell(180, 5, f"{self.company_info['name']} - {self.company_info['email']} - {self.company_info['phone']}", 0, 1, 'C')
    
    def footer_default(self, pdf, footer_y):
        """Pied de page par défaut"""
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.5)
        pdf.line(10, footer_y, 200, footer_y)
        
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["small"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(10, footer_y + 5)
        pdf.cell(190, 5, f"{self.company_info['name']} | {self.company_info['address']}", 0, 1, 'C')
        
        pdf.set_xy(10, footer_y + 10)
        pdf.cell(190, 5, f"Réf: {self.offer_number} | {self.design.get_date_format()}", 0, 1, 'C')
    
    def add_signature_block(self, pdf):
        """Ajoute un bloc de signature selon le layout sélectionné"""
        layout_method = getattr(self, f"signature_{self.current_layout}", self.signature_default)
        layout_method(pdf)
    
    def signature_executive(self, pdf):
        """Bloc de signature pour layout Executive"""
        y_pos = pdf.get_y() + 10
        
        # Titre de la section
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(35, y_pos)
        pdf.cell(160, 10, "Pour accepter cette offre", 0, 1, 'L')
        
        # Bloc de signature
        pdf.set_fill_color(*self.current_theme["secondary"])
        pdf.rect(35, y_pos + 10, 160, 40, 'F')
        
        # Texte
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(40, y_pos + 15)
        pdf.multi_cell(150, 5, "Retournez ce document signé avec la mention 'Bon pour accord' à l'adresse email indiquée ci-dessous, ou contactez-nous directement pour finaliser votre commande.", 0, 'L')
        
        # Zones de signature
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.2)
        
        # Date
        pdf.set_xy(40, y_pos + 30)
        pdf.cell(30, 5, "Date:", 0, 0, 'L')
        pdf.line(70, y_pos + 35, 120, y_pos + 35)
        
        # Signature
        pdf.set_xy(40, y_pos + 40)
        pdf.cell(30, 5, "Signature:", 0, 0, 'L')
        pdf.line(70, y_pos + 45, 180, y_pos + 45)
        
        # Ajuster la position après le bloc de signature
        pdf.set_y(y_pos + 55)
    
    def signature_default(self, pdf):
        """Bloc de signature par défaut"""
        y_pos = pdf.get_y() + 10
        
        # Titre
        pdf.set_font(self.current_theme["fonts"]["body"], 'B', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["primary"])
        pdf.set_xy(15, y_pos)
        pdf.cell(180, 10, "Acceptation de l'offre", 0, 1, 'C')
        
        # Instructions
        pdf.set_font(self.current_theme["fonts"]["body"], '', self.current_theme["font_sizes"]["text"])
        pdf.set_text_color(*self.current_theme["text_color"])
        pdf.set_xy(15, y_pos + 15)
        pdf.multi_cell(180, 5, 
                      f"Pour accepter cette offre, veuillez retourner ce document signé et daté avec la mention 'Bon pour accord' " + 
                      f"à l'adresse email: {self.company_info['email']}", 0, 'C')
        
        # Blocs de signature
        pdf.set_draw_color(*self.current_theme["primary"])
        pdf.set_line_width(0.2)
        
        # Date
        pdf.set_xy(30, y_pos + 30)
        pdf.cell(30, 5, "Date:", 0, 0, 'L')
        pdf.line(60, y_pos + 35, 100, y_pos + 35)
        
        # Signature
        pdf.set_xy(120, y_pos + 30)
        pdf.cell(30, 5, "Signature:", 0, 0, 'L')
        pdf.rect(120, y_pos + 35, 60, 25, 'D')
        
        # Ajuster la position
        pdf.set_y(y_pos + 65)