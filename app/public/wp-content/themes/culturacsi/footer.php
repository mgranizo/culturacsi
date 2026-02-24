	<!-- Footer -->
	<?php
	$hide_footer = false;
	if ( is_singular() ) {
		$hide_footer = get_post_meta( get_the_ID(), '_culturacsi_hide_footer', true );
	}
	if ( ! $hide_footer ) :
	?>
	<footer class="footer-main">
		<div class="container footer-content">
			<h3>ACSI CULTURA</h3>
			<div class="footer-info">
				<?php
				$footer_phone = get_theme_mod( 'footer_phone', '' );
				if ( ! empty( $footer_phone ) ) :
					?>
					<div class="info-item">
						<i class="fas fa-phone"></i>
						<span><?php echo esc_html( $footer_phone ); ?></span>
					</div>
					<?php
				endif;
				
				$footer_email = get_theme_mod( 'footer_email', '' );
				if ( ! empty( $footer_email ) ) :
					?>
					<div class="info-item">
						<i class="fas fa-envelope"></i>
						<a href="mailto:<?php echo esc_attr( $footer_email ); ?>">
							<span><?php echo esc_html( $footer_email ); ?></span>
						</a>
					</div>
					<?php
				endif;
				
				$footer_facebook = get_theme_mod( 'footer_facebook', '' );
				if ( ! empty( $footer_facebook ) ) :
					?>
					<div class="info-item">
						<a href="<?php echo esc_url( $footer_facebook ); ?>" target="_blank" rel="noopener noreferrer">
							<i class="fab fa-facebook-f"></i>
							<span><?php esc_html_e( 'Facebook', 'culturacsi' ); ?></span>
						</a>
					</div>
					<?php
				endif;
				
				$footer_instagram = get_theme_mod( 'footer_instagram', '' );
				if ( ! empty( $footer_instagram ) ) :
					?>
					<div class="info-item">
						<a href="<?php echo esc_url( $footer_instagram ); ?>" target="_blank" rel="noopener noreferrer">
							<i class="fab fa-instagram"></i>
							<span><?php esc_html_e( 'Instagram', 'culturacsi' ); ?></span>
						</a>
					</div>
					<?php
				endif;
				?>
			</div>
		</div>
	</footer>
	<?php endif; ?>

	<?php wp_footer(); ?>

	</div><!-- .site-inner -->
</div><!-- .site-wrapper -->
</div><!-- .site-outer -->
</body>
</html>
