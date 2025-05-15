import React from 'react';

const CoffeeOrderForm = ({ coffeeOrder, handleCoffeeOrderChange, handleOrderSubmit, isOrderLoading }) => {
  return (
    <div className="coffee-order-form component-panel section-mb">
      <form onSubmit={handleOrderSubmit} className="coffee-form">
        <div className="form-group">
          <label>Type de café:</label>
          <div className="type-selector">
            {['Espresso', 'Lungo', 'Cappuccino', 'Latte'].map((type) => (
              <button
                type="button"
                key={type}
                className={`type-button ${coffeeOrder.type === type ? 'active' : ''}`}
                onClick={() => handleCoffeeOrderChange({ target: { name: 'type', value: type } })}
              >
                {type}
              </button>
            ))}
          </div>
        </div>

        <div className="form-group">
          <label>Intensité:</label>
          <div className="intensity-selector">
            <input
              type="range"
              id="intensite"
              name="intensite"
              min="1"
              max="10"
              value={coffeeOrder.intensite}
              onChange={handleCoffeeOrderChange}
              className="intensity-slider"
              style={{ '--slider-percentage': `${((coffeeOrder.intensite - 1) / 9) * 100}%` }}
            />
            <span className="intensity-value">{coffeeOrder.intensite}</span>
          </div>
        </div>

        <div className="form-group">
          <label>Taille:</label>
          <div className="size-selector">
            {['Petit', 'Moyen', 'Grand'].map((size) => (
              <button
                type="button"
                key={size}
                className={`size-button ${coffeeOrder.taille === size ? 'active' : ''}`}
                onClick={() => handleCoffeeOrderChange({ target: { name: 'taille', value: size } })}
              >
                {size}
              </button>
            ))}
          </div>
        </div>
        
        <button type="submit" className="submit-order-btn" disabled={isOrderLoading}>
          {isOrderLoading ? 'Envoi en cours...' : 'Passer la Commande'}
        </button>
      </form>
    </div>
  );
};

export default CoffeeOrderForm; 