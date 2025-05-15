import React from 'react';
import animationGif from '../assets/animation.gif';
import emptyCoffeePng from '../assets/empty_coffee.png';

const CurrentCoffeeAnimation = ({ processingOrders, handleStopProcessingOrder, cancellingOrderId }) => {
  const isAnyOrderProcessing = processingOrders && processingOrders.length > 0;

  return (
    <div className="current-coffee-animation component-panel section-mb section-mt">
      <p className="current-coffee-title">Café en Préparation</p>
      {isAnyOrderProcessing ? (
        processingOrders.map(order => (
          <div key={order.id} className="processing-order-details">
            <p className="processing-order-info">
              <strong>{order.type} - {order.taille}</strong> - Intensité <strong>{order.intensite}</strong>
            </p>
            <button 
              onClick={() => handleStopProcessingOrder(order.id)}
              className="stop-processing-btn cancel-order-btn"
              disabled={cancellingOrderId === order.id}
              title="Stopper la préparation"
            >
              {cancellingOrderId === order.id ? 'Arrêt...' : '⏹️ Stopper'}
            </button>
          </div>
        ))
      ) : (
        <p className="empty-message">En Attente de Commande</p>
      )}

      <div className="coffee-animation-container"> 
        <img 
          src={isAnyOrderProcessing ? animationGif : emptyCoffeePng} 
          alt={isAnyOrderProcessing ? "Café en préparation..." : "Aucun café en préparation"}
          className="coffee-visual" 
        />
      </div>
    </div>
  );
};

export default CurrentCoffeeAnimation; 